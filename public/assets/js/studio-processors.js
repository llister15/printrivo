'use strict';
/* Pixel algorithms are shared by the processing worker and cooperative fallback. */
(() => {
 const clamp=(v,min=0,max=255)=>Math.max(min,Math.min(max,v));
 function canvas(w,h){if(!Number.isInteger(w)||!Number.isInteger(h)||w<1||h<1||w>30000||h>30000||w*h>256000000)throw new Error('Use dimensions up to 30,000 px per side and 256 MP.');const c=typeof OffscreenCanvas!=='undefined'?new OffscreenCanvas(w,h):document.createElement('canvas');c.width=w;c.height=h;return c;}
 function copy(source){const out=canvas(source.width,source.height);out.getContext('2d').drawImage(source,0,0);return out;}
 const pause=()=>new Promise(r=>setTimeout(r,0));
 async function hasArtwork(source,{cancelled=()=>false}={}){
   const own=typeof source.getContext!=='function',surface=own?copy(source):source;
   try{
     const ctx=surface.getContext('2d',{willReadFrequently:true});
     if(!ctx)throw new Error('Your browser could not read this image. Try a smaller image.');
     for(let y=0;y<surface.height;y+=64){
       const data=ctx.getImageData(0,y,surface.width,Math.min(64,surface.height-y)).data;
       for(let i=3;i<data.length;i+=4)if(data[i]>0)return true;
       await pause();if(cancelled())throw new DOMException('Canceled','AbortError');
     }
     return false;
   }finally{if(own)surface.width=surface.height=1;}
 }
 async function preserveArtwork(source,result,options={}){
   if(!await hasArtwork(result,options)&&await hasArtwork(source,options))
     throw new Error('These settings would remove all visible artwork. Your image has been kept. Reduce the cleanup or removal settings.');
 }
 async function analyze(source,{cancelled=()=>false}={}){
   const c=copy(source),x=c.getContext('2d',{willReadFrequently:true}),w=c.width,h=c.height;
   let visible=0,clear=0,partial=0,low=0,touch=0,minX=w,minY=h,maxX=-1,maxY=-1,edgeN=0,edgeSum=[0,0,0],edgeSquare=[0,0,0];
   // Read strips to avoid a second full-size RGBA allocation during preflight.
   for(let y0=0;y0<h;y0+=128){const rows=Math.min(128,h-y0),d=x.getImageData(0,y0,w,rows).data;
     for(let y=0;y<rows;y++)for(let px=0;px<w;px++){const i=(y*w+px)*4,a=d[i+3],py=y+y0;if(a===0)clear++;else{visible++;if(a<255)partial++;if(a<26)low++;if(a>0){minX=Math.min(minX,px);minY=Math.min(minY,py);maxX=Math.max(maxX,px);maxY=Math.max(maxY,py);}}
       if(px===0||py===0||px===w-1||py===h-1){if(a>0)touch++;if(a>245){edgeN++;for(let k=0;k<3;k++){edgeSum[k]+=d[i+k];edgeSquare[k]+=d[i+k]**2;}}}
     }await pause();if(cancelled())throw new DOMException('Canceled','AbortError');
   }
   const bounds=maxX<0?null:{x:minX,y:minY,width:maxX-minX+1,height:maxY-minY+1};
   const edgeColor=edgeN?edgeSum.map(v=>Math.round(v/edgeN)):null,edgeDeviation=edgeN?Math.sqrt(edgeSquare.reduce((n,v,k)=>n+Math.max(0,v/edgeN-(edgeSum[k]/edgeN)**2),0)/3):0;
   c.width=c.height=1;
   return {width:w,height:h,visible,clear,partial,low,touch,bounds,edgeColor,solidEdge:edgeN>Math.max(1,(2*w+2*h-4)*.8)&&edgeDeviation<15};
 }
 const processors={};
 processors.resize=async(source,settings)=>{
   const out=canvas(settings.width,settings.height),ctx=out.getContext('2d');
   if(!ctx)throw new Error('Your browser could not allocate this image. Try a lower DPI.');
   ctx.imageSmoothingEnabled=true;ctx.imageSmoothingQuality='high';
   ctx.drawImage(source,0,0,out.width,out.height);
   return {canvas:out};
 };
 async function render(source,name,settings={},options={}){
   if(!processors[name])throw new Error('Unknown artwork processor.');
   const result=await processors[name](source,settings,options);
   if(!options.view||options.view==='result'||options.view==='transparency'){
     try{await preserveArtwork(source,result.canvas,options);}catch(error){result.canvas.width=result.canvas.height=1;throw error;}
   }
   return result;
 }
 globalThis.StudioProcessors={canvas,copy,analyze,hasArtwork,preserveArtwork,render,processors,clamp,pause};
})();

(() => {
 const P=StudioProcessors,{canvas,copy,clamp,pause}=P;
 const number=(s,key,min,max,fallback)=>{const v=s[key]??fallback;if(typeof v!=='number'||!Number.isFinite(v)||v<min||v>max)throw new Error(`Enter ${key} from ${min} to ${max}.`);return v;};
 const rgb=hex=>{if(!/^#[0-9a-f]{6}$/i.test(hex))throw new Error('Choose a valid color.');return [1,3,5].map(i=>parseInt(hex.slice(i,i+2),16));};
 const luma=(r,g,b)=>.2126*r+.7152*g+.0722*b;
 // Red-mean weighted RGB distance approximates perceptual differences while
 // keeping full-resolution color matching practical. Result is normalized 0–100.
 function distance(r,g,b,color){const mean=(r+color[0])/2;return Math.sqrt((2+mean/256)*(r-color[0])**2+4*(g-color[1])**2+(2+(255-mean)/256)*(b-color[2])**2)/7.65;}
 async function pixelTool(source,s,o,operation){const out=copy(source),x=out.getContext('2d',{willReadFrequently:true});for(let y=0;y<out.height;y+=64){const h=Math.min(64,out.height-y),d=x.getImageData(0,y,out.width,h);for(let i=0;i<d.data.length;i+=4)operation(d.data,i);x.putImageData(d,0,y);await pause();if(o.cancelled?.())throw new DOMException('Canceled','AbortError');}return {canvas:out};}
 function mask(d,i,value){d[i]=d[i+1]=d[i+2]=clamp(value*255);d[i+3]=255;}
 P.processors.removeColor=async(source,s,o)=>{
   const color=rgb(s.color),tolerance=number(s,'tolerance',0,100,10),soft=number(s,'softness',0,100,10);
   return pixelTool(source,s,o,(d,i)=>{const a=d[i+3],dist=distance(d[i],d[i+1],d[i+2],color);const match=dist<=tolerance?1:soft?clamp(1-(dist-tolerance)/soft,0,1):0;const preserve=s.preserve&&a>0&&a<255;const result=preserve?a:Math.round(a*(1-match));if(o.view==='mask')mask(d,i,(a-result)/255);else{d[i+3]=result;if(!result)d[i]=d[i+1]=d[i+2]=0;}});
 };
 P.processors.replaceColor=async(source,s,o)=>{
   const from=rgb(s.from),to=rgb(s.to),tolerance=number(s,'tolerance',0,100,10),base=Math.max(1,luma(...from));
   return pixelTool(source,s,o,(d,i)=>{const matched=d[i+3]>0&&distance(d[i],d[i+1],d[i+2],from)<=tolerance;if(o.view==='mask'){mask(d,i,matched?d[i+3]/255:0);return;}if(!matched)return;const ratio=s.shading?luma(d[i],d[i+1],d[i+2])/base:1;for(let k=0;k<3;k++)d[i+k]=clamp(to[k]*ratio);});
 };
 // Sliding-window extrema/means use O(pixels) work, independent of radius.
 async function neighborhood(input,w,h,r,kind,cancelled){
   if(r<1)return input.slice();const tmp=new Uint8ClampedArray(input.length),out=new Uint8ClampedArray(input.length);
   const pass=async(src,dst,rows,cols,stride,rowStride)=>{
     for(let row=0;row<rows;row++){
       if(kind==='mean'){let sum=0;for(let k=-r;k<=r;k++)sum+=src[row*rowStride+clamp(k,0,cols-1)*stride];for(let col=0;col<cols;col++){dst[row*rowStride+col*stride]=sum/(2*r+1);sum-=src[row*rowStride+clamp(col-r,0,cols-1)*stride];sum+=src[row*rowStride+clamp(col+r+1,0,cols-1)*stride];}}
       else{const queue=new Int32Array(cols);let head=0,tail=0,right=-1;for(let col=0;col<cols;col++){const end=Math.min(cols-1,col+r);while(right<end){right++;const v=src[row*rowStride+right*stride];while(tail>head&&(kind==='max'?src[row*rowStride+queue[tail-1]*stride]<=v:src[row*rowStride+queue[tail-1]*stride]>=v))tail--;queue[tail++]=right;}while(head<tail&&queue[head]<col-r)head++;dst[row*rowStride+col*stride]=src[row*rowStride+queue[head]*stride];}}
       if(row%64===0){await pause();if(cancelled?.())throw new DOMException('Canceled','AbortError');}
     }
   };await pass(input,tmp,h,w,1,w);await pass(tmp,out,w,h,w,1);return out;
 }
 async function alphaTool(source,s,o,edges=false){
   const out=copy(source),x=out.getContext('2d',{willReadFrequently:true}),im=x.getImageData(0,0,out.width,out.height),d=im.data,w=out.width,h=out.height,scale=w/(o.documentWidth||w);
   const cut=number(s,'cut',0,100,0)*2.55,threshold=number(s,'threshold',0,255,0),hard=number(s,'hardness',0,100,0)/100,cleanup=number(s,'cleanup',0,100,0)/100;
   let a=new Uint8ClampedArray(w*h);for(let i=0;i<a.length;i++)a[i]=d[i*4+3];
   if(cleanup){const eroded=await neighborhood(a,w,h,Math.max(1,Math.round(2*scale)),'min',o.cancelled);for(let i=0;i<a.length;i++)a[i]=a[i]*(1-cleanup)+eroded[i]*cleanup;}
   if(edges){
     const smooth=number(s,'smooth',0,100,0)/100;if(smooth){const blurred=await neighborhood(a,w,h,Math.max(1,Math.round(2*scale)),'mean',o.cancelled);for(let i=0;i<a.length;i++)a[i]=a[i]*(1-smooth)+blurred[i]*smooth;}
     const speckles=number(s,'speckles',0,100,0);if(speckles){const r=Math.max(1,Math.round(speckles/25*scale)),eroded=await neighborhood(a,w,h,r,'min',o.cancelled);a=await neighborhood(eroded,w,h,r,'max',o.cancelled);}
     const holes=number(s,'holes',0,100,0);if(holes){const r=Math.max(1,Math.round(holes/25*scale)),dilated=await neighborhood(a,w,h,r,'max',o.cancelled);a=await neighborhood(dilated,w,h,r,'min',o.cancelled);}
   }
   const fringe=edges&&s.fringe?rgb(s.fringeColor==='black'?'#000000':s.fringeColor==='custom'?s.custom:'#ffffff'):null;
   for(let y=0;y<h;y++){
     for(let px=0;px<w;px++){const p=y*w+px,i=p*4,old=d[i+3];let value=a[p];if(value<cut||value<threshold)value=0;
       if(s.isolated&&value){let neighbors=0;for(let dy=-1;dy<=1;dy++)for(let dx=-1;dx<=1;dx++){if(!dx&&!dy)continue;const nx=px+dx,ny=y+dy;if(nx>=0&&ny>=0&&nx<w&&ny<h&&a[ny*w+nx]>0)neighbors++;}if(!neighbors)value=0;}
       if(hard&&value>0&&value<255)value=value*(1-hard)+(value>=128?255:0)*hard;
       // Alpha decontamination removes a selected matte from partially opaque
       // edge RGB, without inventing a subject or changing opaque interiors.
       if(fringe&&old>0&&old<255){const opacity=old/255;let matte=fringe;if(s.fringeColor==='auto'){let sum=0,n=0;for(const [dx,dy] of [[-1,0],[1,0],[0,-1],[0,1]]){const nx=px+dx,ny=y+dy;if(nx>=0&&ny>=0&&nx<w&&ny<h){const j=(ny*w+nx)*4;if(d[j+3]>245){sum+=luma(d[j],d[j+1],d[j+2]);n++;}}}matte=n&&sum/n>127?[0,0,0]:[255,255,255];}for(let k=0;k<3;k++)d[i+k]=clamp((d[i+k]-matte[k]*(1-opacity))/Math.max(.1,opacity));}
       d[i+3]=value;if(s.cleanRGB&&!value)d[i]=d[i+1]=d[i+2]=0;
       if(o.view==='mask')mask(d,i,value/255);else if(o.view==='transparency')d[i]=d[i+1]=d[i+2]=255;
     }if(y%64===0){await pause();if(o.cancelled?.())throw new DOMException('Canceled','AbortError');}
   }x.putImageData(im,0,0);return {canvas:out};
 }
 P.processors.alphaCleanup=(source,s,o)=>alphaTool(source,s,o);
 P.processors.edges=(source,s,o)=>alphaTool(source,s,o,true);
 Object.assign(P,{number,rgb,luma,distance,pixelTool,mask,neighborhood});
})();

(() => {
 const P=StudioProcessors,{canvas,copy,number,clamp,pause}=P;
 function dimensions(source,o){return {w:o.documentWidth||source.width,h:o.documentHeight||source.height,scale:source.width/(o.documentWidth||source.width)};}
 P.processors.padding=async(source,s,o)=>{
   const {w,h,scale}=dimensions(source,o),factor=s.unit==='inches'?number(o,'dpi',1,2400,300):1;
   const sides=['top','right','bottom','left'].map(k=>Math.round(number(s,k,0,30000,50)*factor));const [top,right,bottom,left]=sides,nw=w+left+right,nh=h+top+bottom;
   // Validate document dimensions before allocating even a preview.
   if(nw>30000||nh>30000||nw*nh>256000000)throw new Error('Padding would exceed 30,000 px per side or 256 MP.');
   const out=canvas(Math.max(1,Math.round(nw*scale)),Math.max(1,Math.round(nh*scale)));out.getContext('2d').drawImage(source,Math.round(left*scale),Math.round(top*scale));
   return {canvas:out,geometry:{offsetX:left,offsetY:top,width:nw,height:nh}};
 };
 P.processors.center=async(source,s,o)=>{
   const {w,h,scale}=dimensions(source,o),a=o.bounds?{bounds:o.bounds}:await P.analyze(source,o),b=a.bounds;if(!b)throw new Error('No visible artwork to center.');
   const full=o.bounds?b:{x:b.x/scale,y:b.y/scale,width:b.width/scale,height:b.height/scale};
   const dx=Math.round((w-full.width)/2-full.x),dy=Math.round((h-full.height)/2-full.y),out=canvas(source.width,source.height);out.getContext('2d').drawImage(source,Math.round(dx*scale),Math.round(dy*scale));return {canvas:out,geometry:{offsetX:dx,offsetY:dy,width:w,height:h}};
 };
 // Exact squared Euclidean distance transform (separable lower envelopes),
 // giving round outlines without a width-dependent per-pixel neighborhood loop.
 async function distanceMask(alpha,w,h,inside,cancelled){
   const tmp=new Float32Array(w*h),out=new Float32Array(w*h),n=Math.max(w,h),f=new Float64Array(n),d=new Float64Array(n),v=new Int32Array(n),z=new Float64Array(n+1),INF=1e12;
   function transform(length){let k=0;v[0]=0;z[0]=-Infinity;z[1]=Infinity;for(let q=1;q<length;q++){let intersection;do{const p=v[k];intersection=((f[q]+q*q)-(f[p]+p*p))/(2*q-2*p);if(intersection<=z[k])k--;else break;}while(k>=0);k++;v[k]=q;z[k]=intersection;z[k+1]=Infinity;}k=0;for(let q=0;q<length;q++){while(z[k+1]<q)k++;d[q]=(q-v[k])**2+f[v[k]];}}
   for(let y=0;y<h;y++){for(let x=0;x<w;x++)f[x]=(inside?alpha[y*w+x]===0:alpha[y*w+x]>0)?0:INF;transform(w);for(let x=0;x<w;x++)tmp[y*w+x]=d[x];if(y%64===0){await pause();if(cancelled?.())throw new DOMException('Canceled','AbortError');}}
   for(let x=0;x<w;x++){for(let y=0;y<h;y++)f[y]=tmp[y*w+x];transform(h);for(let y=0;y<h;y++){const distance=Math.sqrt(d[y]);out[y*w+x]=inside?Math.min(distance,x+1,y+1,w-x,h-y):distance;}if(x%64===0){await pause();if(cancelled?.())throw new DOMException('Canceled','AbortError');}}
   return out;
 }
 P.processors.outline=async(source,s,o)=>{
   const {scale}=dimensions(source,o),width=number(s,'width',1,100,10)*scale,opacity=number(s,'opacity',0,100,100)/100,color=P.rgb(s.color),base=copy(source),x=base.getContext('2d',{willReadFrequently:true}),im=x.getImageData(0,0,base.width,base.height),d=im.data,w=base.width,h=base.height,a=new Uint8ClampedArray(w*h);
   for(let i=0;i<a.length;i++)a[i]=d[i*4+3];let expanded,contracted;
   const outer=s.position==='inside'?0:s.position==='center'?width/2:width,inner=s.position==='outside'?0:s.position==='center'?width/2:width;
   const mask=canvas(w,h),mc=mask.getContext('2d'),m=mc.createImageData(w,h);
   if(s.corner==='sharp'){if(outer)expanded=await P.neighborhood(a,w,h,Math.max(1,Math.round(outer)),'max',o.cancelled);if(inner)contracted=await P.neighborhood(a,w,h,Math.max(1,Math.round(inner)),'min',o.cancelled);}
   else{if(outer)expanded=await distanceMask(a,w,h,false,o.cancelled);if(inner)contracted=await distanceMask(a,w,h,true,o.cancelled);}
   for(let i=0;i<a.length;i++){const original=a[i]/255,outside=outer?(s.corner==='sharp'?expanded[i]/255:clamp(outer+.5-expanded[i],0,1)):0,inside=inner?(s.corner==='sharp'?1-contracted[i]/255:clamp(inner+.5-contracted[i],0,1)):0;
     const border=s.corner==='sharp'&&inner&&Math.min(i%w+1,Math.floor(i/w)+1,w-i%w,h-Math.floor(i/w))<=inner?1:0;
     const value=Math.max(outside*(1-original),Math.max(inside,border)*original)*opacity;for(let k=0;k<3;k++)m.data[i*4+k]=color[k];m.data[i*4+3]=value*255;
     if(i%1048576===0){await pause();if(o.cancelled?.())throw new DOMException('Canceled','AbortError');}
   }
   mc.putImageData(m,0,0);x.drawImage(mask,0,0);return {canvas:base};
 };
 Object.assign(P,{dimensions,distanceMask});
})();

(() => {
 const P=StudioProcessors,{copy,canvas,number,clamp,pause}=P;
 P.processors.sharpen=async(source,s,o)=>{
   const amount=number(s,'amount',0,200,80)/100,radius=number(s,'radius',.1,5,1)*source.width/(o.documentWidth||source.width),threshold=number(s,'threshold',0,255,3),out=copy(source),x=out.getContext('2d',{willReadFrequently:true}),im=x.getImageData(0,0,out.width,out.height),d=im.data,w=out.width,h=out.height,n=w*h,r=Math.max(1,Math.round(radius));
   let alpha=new Uint8ClampedArray(n);for(let i=0;i<n;i++)alpha[i]=d[i*4+3];
   for(let pass=0;pass<3;pass++)alpha=await P.neighborhood(alpha,w,h,r,'mean',o.cancelled);
   for(let channel=0;channel<3;channel++){
     let blurred=new Uint8ClampedArray(n);for(let i=0;i<n;i++)blurred[i]=d[i*4+channel]*d[i*4+3]/255;
     for(let pass=0;pass<3;pass++)blurred=await P.neighborhood(blurred,w,h,r,'mean',o.cancelled);
     for(let i=0;i<n;i++){const j=i*4+channel;if(!d[i*4+3]||!alpha[i])continue;const low=blurred[i]*255/alpha[i],difference=d[j]-low;if(Math.abs(difference)>=threshold)d[j]=clamp(d[j]+difference*amount*Math.min(1,radius));}
   }x.putImageData(im,0,0);return {canvas:out};
 };
 P.processors.crop=async(source,s,o)=>{
   const {w,h,scale}=P.dimensions(source,o),factor=s.unit==='inches'?number(o,'dpi',1,2400,300):1;
   const x=Math.round(number(s,'x',0,30000,0)*factor),y=Math.round(number(s,'y',0,30000,0)*factor),width=Math.round(number(s,'width',.001,30000,w)*factor),height=Math.round(number(s,'height',.001,30000,h)*factor),angle=number(s,'angle',-45,45,0);
   if(width<1||height<1||x+width>w||y+height>h)throw new Error('Crop must stay inside the current canvas.');
   let base=source;if(angle){base=canvas(source.width,source.height);const c=base.getContext('2d');c.translate(source.width/2,source.height/2);c.rotate(angle*Math.PI/180);c.drawImage(source,-source.width/2,-source.height/2);}
   const out=canvas(Math.max(1,Math.round(width*scale)),Math.max(1,Math.round(height*scale)));out.getContext('2d').drawImage(base,x*scale,y*scale,width*scale,height*scale,0,0,out.width,out.height);
   return {canvas:out,geometry:{offsetX:-x,offsetY:-y,width,height,flatten:angle!==0}};
 };
 async function paletteFor(d,count,cancelled){
   const bins=new Map(),step=Math.max(1,Math.floor(d.length/4/150000));
   for(let i=0;i<d.length;i+=4*step){if(!d[i+3])continue;const key=(d[i]>>3)*1024+(d[i+1]>>3)*32+(d[i+2]>>3),weight=d[i+3]/255;let b=bins.get(key);if(!b){b=[0,0,0,0];bins.set(key,b);}for(let k=0;k<3;k++)b[k]+=d[i+k]*weight;b[3]+=weight;}
   const points=[...bins.values()].map(b=>[b[0]/b[3],b[1]/b[3],b[2]/b[3],b[3]]).sort((a,b)=>b[3]-a[3]);if(!points.length)return [[0,0,0]];
   const palette=[points[0].slice(0,3)],dist=(a,b)=>(a[0]-b[0])**2+(a[1]-b[1])**2+(a[2]-b[2])**2;
   while(palette.length<Math.min(count,points.length)){let best=points[0],score=-1;for(const p of points){const value=Math.min(...palette.map(c=>dist(p,c)))*Math.sqrt(p[3]);if(value>score){score=value;best=p;}}palette.push(best.slice(0,3));}
   for(let iteration=0;iteration<8;iteration++){const sums=palette.map(()=>[0,0,0,0]);for(const p of points){let index=0,best=Infinity;palette.forEach((c,i)=>{const value=dist(p,c);if(value<best){best=value;index=i;}});for(let k=0;k<3;k++)sums[index][k]+=p[k]*p[3];sums[index][3]+=p[3];}for(let i=0;i<palette.length;i++)if(sums[i][3])palette[i]=sums[i].slice(0,3).map(v=>v/sums[i][3]);await pause();if(cancelled?.())throw new DOMException('Canceled','AbortError');}
   return palette.map(c=>c.map(v=>Math.round(v)));
 }
 P.processors.posterize=async(source,s,o)=>{
   const count=Math.round(number(s,'colors',2,16,4)),out=copy(source),x=out.getContext('2d',{willReadFrequently:true}),im=x.getImageData(0,0,out.width,out.height),d=im.data,w=out.width,h=out.height;
   const palette=s.palette?.length?s.palette.map(P.rgb):await paletteFor(d,count,o.cancelled);if(palette.length>16)throw new Error('Use at most 16 palette colors.');
   let current=new Float32Array((w+2)*3),next=new Float32Array((w+2)*3);
   for(let y=0;y<h;y++){const reverse=y%2,dir=reverse?-1:1;for(let step=0;step<w;step++){const px=reverse?w-1-step:step,i=(y*w+px)*4,e=(px+1)*3;if(!d[i+3]&&s.preserve)continue;const color=[0,1,2].map(k=>clamp(d[i+k]+(s.dither?current[e+k]:0)));let best=palette[0],distance=Infinity;for(const p of palette){const value=(p[0]-color[0])**2+(p[1]-color[1])**2+(p[2]-color[2])**2;if(value<distance){distance=value;best=p;}}for(let k=0;k<3;k++){const error=color[k]-best[k];d[i+k]=best[k];if(s.dither){current[e+dir*3+k]+=error*7/16;next[e-dir*3+k]+=error*3/16;next[e+k]+=error*5/16;next[e+dir*3+k]+=error/16;}}if(!s.preserve)d[i+3]=255;}
     [current,next]=[next,current];next.fill(0);if(y%32===0){await pause();if(o.cancelled?.())throw new DOMException('Canceled','AbortError');}
   }x.putImageData(im,0,0);return {canvas:out,palette:palette.map(c=>'#'+c.map(v=>v.toString(16).padStart(2,'0')).join(''))};
 };
})();

(() => {
 const P=StudioProcessors;
 P.processors.background=async(source,s,o)=>{
   const out=P.copy(source),c=out.getContext('2d',{willReadFrequently:true}),d=c.getImageData(0,0,out.width,out.height),color=s.source==='custom'?P.rgb(s.color):sampleCornerColor(d,out.width,out.height);
   const result=removeBackground(d,color,P.number(s,'tolerance',0,120,20),P.number(s,'softness',0,40,10),s.area==='all');c.putImageData(result,0,0);return {canvas:out};
 };
 P.processors.adjustments=async(source,s,o)=>{
   const brightness=P.number(s,'brightness',-100,100,0)*2.55,contrast=((100+P.number(s,'contrast',-100,100,0))/100)**2,saturation=P.number(s,'saturation',0,200,100)/100;
   return P.pixelTool(source,s,o,(d,i)=>{const lum=P.luma(d[i],d[i+1],d[i+2]);for(let k=0;k<3;k++)d[i+k]=P.clamp((lum+(d[i+k]-lum)*saturation-128)*contrast+128+brightness);});
 };
})();
