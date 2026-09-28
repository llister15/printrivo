const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const {createCanvas} = require('@napi-rs/canvas');
const root = path.resolve(__dirname, '../..');
const read = name => fs.readFileSync(path.join(root, 'public/assets/js', name), 'utf8');
const document = {createElement: type => {assert.equal(type, 'canvas'); return createCanvas(1, 1);}};
const context = vm.createContext({document, setTimeout, DOMException, console, Uint8ClampedArray, Float32Array, Int32Array});
vm.runInContext(read('studio-processors.js'), context);
const P = context.StudioProcessors;
function image(w=12,h=12){const c=createCanvas(w,h),x=c.getContext('2d');x.fillStyle='#e32b1b';x.fillRect(2,2,w-4,h-4);return c;}
(async()=>{
 const source=image(),before=source.toBuffer('image/png');
 const normal=await P.render(source,'alphaCleanup',{cut:15,cleanup:22,threshold:0,hardness:0,isolated:false,cleanRGB:false},{view:'result'});
 assert.equal(await P.hasArtwork(normal.canvas),true,'recorded cleanup settings retain opaque art');
 const faint=createCanvas(12,12);faint.getContext('2d').fillStyle='rgba(255,0,0,.1)';faint.getContext('2d').fillRect(2,2,8,8);
 for(const view of ['result','transparency'])await assert.rejects(P.render(faint,'alphaCleanup',{cut:15},{view}),/remove all visible artwork/);
 await assert.rejects(P.render(source,'removeColor',{color:'#e32b1b',tolerance:100,softness:0},{view:'result'}),/remove all visible artwork/);
 const mask=await P.render(faint,'alphaCleanup',{cut:15},{view:'mask'});
 assert.equal(await P.hasArtwork(mask.canvas),true,'diagnostic masks remain available');
 assert.deepEqual(source.toBuffer('image/png'),before,'rejected and successful edits leave source intact');
 const partial=await P.render(faint,'alphaCleanup',{cut:0,threshold:0,hardness:0},{});
 assert.ok(partial.canvas.getContext('2d').getImageData(4,4,1,1).data[3]>0,'soft shading preserved');
 assert.equal(await P.hasArtwork(createCanvas(200,200)),false);
 // Exercise both bundled neural models on real pixels, using memory IO instead of an HTTP server.
 const tf=require(path.join(root,'public/assets/vendor/studio-ai/tf-4.11.0.min.js'));
 const originalLoad=tf.loadLayersModel;
 const localTF=Object.create(tf);
 localTF.loadLayersModel=async url=>{
   const scale=new URL(url).pathname.includes('/x2/')?'x2':'x4';
   const dir=path.join(root,'public/assets/vendor/studio-ai/models',scale);
   const json=JSON.parse(fs.readFileSync(path.join(dir,'model.json')));
   const buffers=json.weightsManifest.flatMap(group=>group.paths.map(file=>fs.readFileSync(path.join(dir,file))));
   const buffer=Buffer.concat(buffers);
   return originalLoad(tf.io.fromMemory({modelTopology:json.modelTopology,weightSpecs:json.weightsManifest.flatMap(group=>group.weights),weightData:buffer.buffer.slice(buffer.byteOffset,buffer.byteOffset+buffer.byteLength)}));
 };
 document.currentScript={src:'http://localhost/assets/js/studio-upscale.js'};
 document.head={append: script=>script.onload()};
 const originalCreate=document.createElement;
 document.createElement=type=>type==='script'?{remove(){}}:originalCreate(type);
 context.window={tf:localTF};context.URL=URL;
 vm.runInContext(read('studio-upscale.js'),context);
 await tf.setBackend('cpu');await tf.ready();
 const baseline=tf.memory().numTensors;
 for(const scale of [2,4]){
   const output=await context.window.StudioUpscale.resize(source,source.width*scale,source.height*scale);
   assert.equal(output.width,source.width*scale);assert.equal(output.height,source.height*scale);
   const pixels=output.getContext('2d').getImageData(0,0,output.width,output.height).data;
   assert.equal(pixels[3],0,'transparent corner preserved');
   assert.equal(pixels[((6*scale)*output.width+6*scale)*4+3],255,'opaque center preserved');
   const smooth=await P.render(source,'resize',{width:output.width,height:output.height});
   const comparison=smooth.canvas.getContext('2d').getImageData(0,0,output.width,output.height).data;
   let different=0;for(let i=0;i<pixels.length;i+=4){assert.equal(pixels[i+3],comparison[i+3],'alpha equals ordinary source scaling');if(pixels[i+3]===255&&pixels[i]!==comparison[i])different++;}
   assert.ok(different>0,'neural RGB output differs from ordinary smoothing');
   assert.deepEqual(source.toBuffer('image/png'),before);
   assert.equal(tf.memory().numTensors,baseline,'model and prediction tensors released');
 }
 const wide=image(68,8),scaled=await context.window.StudioUpscale.resize(wide,102,12);
 assert.equal(scaled.width,102);assert.equal(scaled.getContext('2d').getImageData(95,6,3,1).data[3],255,'tile boundary remains opaque');
 await assert.rejects(context.window.StudioUpscale.resize(source,100,100),/up to 4/);
 await assert.rejects(context.window.StudioUpscale.resize({width:5000,height:4000},10000,8000),/16 MP/);
 localTF.loadLayersModel=async()=>{throw Error('Model unavailable');};
 await assert.rejects(context.window.StudioUpscale.resize(source,24,24),/original image is unchanged/);
 assert.deepEqual(source.toBuffer('image/png'),before);
 console.log('PASS: real cleanup pixels, empty-result protection, diagnostic masks, 2×/4× AI inference, alpha preservation, tiled arbitrary sizing, tensor cleanup, size limits and model failure.');
})().catch(error=>{console.error(error);process.exitCode=1;});
