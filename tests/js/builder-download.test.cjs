const {readFileSync}=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const source=readFileSync(require('node:path').join(__dirname,'../../app/Views/tools/gang_sheet.php'),'utf8').match(/<script>\s*([\s\S]*?)<\/script>/)[1];
const nodes=new Map();let progress=false,clicks=0,fail=false,revoked=[];
function node(key){if(!nodes.has(key))nodes.set(key,{value:key==='#sheetLength'?'12':'',style:{},setAttribute(){},classList:{toggle(){}},querySelector(){return node('img')},addEventListener(){},showModal(){assert.equal(progress,false);this.open=true},click(){assert.equal(node('#downloadResult').open,true);assert.equal(this.hidden,false);clicks++}});return nodes.get(key)}
function chunk(type,data=Buffer.alloc(0)){const out=Buffer.alloc(data.length+12);out.writeUInt32BE(data.length);out.write(type,4);data.copy(out,8);return out}
const png=new Blob([Buffer.from('89504e470d0a1a0a','hex'),chunk('IHDR',Buffer.alloc(13)),chunk('pHYs',Buffer.alloc(9)),chunk('IDAT',Buffer.from([1,2,3])),chunk('IEND')],{type:'image/png'});
const ctx=vm.createContext({Blob,TextEncoder,console,URL:{createObjectURL:()=>`blob:download-${clicks}`,revokeObjectURL:url=>revoked.push(url)},window:{},document:{querySelector:key=>key==='dialog[open]'?null:node(key),querySelectorAll:()=>[],createElement:()=>({getContext:()=>({save(){},translate(){},rotate(){},drawImage(){},restore(){}}),toBlob:cb=>cb(fail?null:png)})},PrintProgress:{start:async()=>{progress=true},paint:async()=>{},update(){},finish(){progress=false}}});
vm.runInContext(source,ctx);
const run=code=>vm.runInContext(code,ctx);
(async()=>{
 run("loadExportImage=async()=>({});items.push({x:0,y:0,w:1,h:1,rotation:0,asset:{url:'blob:art'}})");
 await run('savePrintSheet()');
 assert.equal(clicks,1);assert.equal(revoked.length,0);assert.equal(node('#saveSheet').disabled,false);
 assert.match(node('#downloadFile').download,/16x12in-300dpi.png/);
 await run('savePrintSheet()');assert.equal(clicks,2);assert.equal(revoked.length,1);
 fail=true;await run('savePrintSheet()');assert.equal(clicks,2);assert.equal(node('#downloadFile').hidden,true);assert.match(node('#downloadMessage').textContent,/could not encode/);assert.equal(run('items.length'),1);assert.equal(run('exporting'),false);
 node('#sheetLength').value='200';await run('savePrintSheet()');assert.match(node('#downloadMessage').textContent,/too large/);
 run("selected=items[0];selected.el={remove(){}};globalThis.prevented=false");
 run("deleteSelectedArtwork({key:'Delete',target:{closest:()=>true},preventDefault(){prevented=true}})");assert.equal(run('items.length'),1);
 run("exporting=true;deleteSelectedArtwork({key:'Delete',target:{},preventDefault(){prevented=true}});exporting=false");assert.equal(run('items.length'),1);
 run("deleteSelectedArtwork({key:'Delete',target:{},preventDefault(){prevented=true}})");assert.equal(run('items.length'),0);assert.equal(run('selected'),null);assert.equal(run('prevented'),true);
 ctx.input=png;
 const output=Buffer.from(await (await run('addPngDpi(input,300)')).arrayBuffer());
 let offset=8,phys=0,idat;
 while(offset<output.length){const length=output.readUInt32BE(offset),type=output.toString('ascii',offset+4,offset+8);if(type==='pHYs'){phys++;assert.equal(output.readUInt32BE(offset+8),11811);assert.equal(output.readUInt32BE(offset+12),11811);assert.equal(output[offset+16],1)}if(type==='IDAT')idat=output.subarray(offset+8,offset+8+length);offset+=12+length}
 assert.equal(phys,1);assert.deepEqual(idat,Buffer.from([1,2,3]));
 console.log('Passed: download retry link, retained URL, replacement cleanup, visible encoding/size errors, preserved artwork, and 300 DPI PNG metadata.');
})().catch(error=>{console.error(error);process.exitCode=1});
