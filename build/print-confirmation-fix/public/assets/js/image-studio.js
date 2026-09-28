'use strict';
(() => {
    const $ = id => document.getElementById(id);
    const MAX_PIXELS = 256_000_000, MAX_EDGE = 30000, HISTORY_BYTES = 96_000_000;
    let image = null, original = null, texts = [], selected = '', filename = '', sourceDpi = null;
    let undo = [], redo = [], busy = false, dirty = false, drag = null, nextLayer = 1;
    let measureMode=false, measurement=null, mirrored=false, exportUI=null;
    let halftoneUI = null, preflight = null, production = null, sizeKey = null;
    const canvas = $('canvas'), ctx = canvas.getContext('2d');
    function message(text, error = false) { $('status').textContent = text; $('status').classList.toggle('error', error); }
    function dimensionsOK(w,h) { if (!Number.isFinite(w*h) || w<1 || h<1 || w>MAX_EDGE || h>MAX_EDGE || w*h>MAX_PIXELS) throw new Error(`Requested ${w.toLocaleString()} × ${h.toLocaleString()} pixels (${(w*h/1e6).toFixed(1)} MP). Maximum 30,000 pixels per side and 256 MP. Reduce inches or DPI.`); }
    function makeCanvas(w,h) { dimensionsOK(w,h); const c=document.createElement('canvas'); c.width=w;c.height=h;return c; }
    function snapshot() { return {image, texts:structuredClone(texts), selected,dpi:$('dpi').value,printWidth:$('print-width').value,printHeight:$('print-height').value}; }
    function limitHistory(stack) { while(stack.length>12 || (stack.length>1 && stack.reduce((n,s)=>n+s.image.width*s.image.height*4,0)>HISTORY_BYTES)) stack.shift(); }
    function restore(s) { StudioPreview.cancelAll();image=s.image;texts=structuredClone(s.texts);selected=s.selected;$('dpi').value=s.dpi;$('print-width').value=s.printWidth;$('print-height').value=s.printHeight;sizeKey=image.width+'x'+image.height;sync();halftoneUI?.dpiChanged(); }
    function checkpoint() { StudioPreview.cancelAll();undo.push(snapshot());limitHistory(undo);redo=[];dirty=true; }
    async function edit(label, operation) {
        if (!image || busy) return;
        StudioPreview.cancelAll();measurement=null;$('measurement').hidden=true;
        busy=true;buttons();message(label+'…');
        const before=snapshot();
        try { await PrintProgress.start(label+'…'); await operation();undo.push(before);limitHistory(undo);redo=[];dirty=true;sync();message(label+'.'); }
        catch(e) { restore(before);message(e.message || 'The edit could not be completed.',true); }
        finally { busy=false;buttons();PrintProgress.finish(); }
    }
    function buttons() { $('send-print').disabled=!image||busy; $('image-tools').disabled=!image||busy;$('text-tools').disabled=!image||busy;$('export').disabled=!image||busy;$('reset').disabled=!image||busy;$('clear-image').disabled=!image||busy;$('undo').disabled=!undo.length||busy;$('redo').disabled=!redo.length||busy;$('open').disabled=busy;$('open-empty').disabled=busy;$('measure').disabled=!image||busy;$('mirror-preview').disabled=!image||busy;halftoneUI?.refresh();production?.refresh(); }
    function composite() { const out=cloneCanvas(image);paintText(out.getContext('2d'));return out; }
    function paintText(c) { for(const t of texts) {c.font=`${t.weight} ${t.size}px ${t.font}`;c.fillStyle=t.color;c.textBaseline='top';t.text.split('\n').forEach((line,i)=>c.fillText(line,t.x,t.y+i*t.size*1.2));} }
    function textBounds(t) {ctx.font=`${t.weight} ${t.size}px ${t.font}`;return {w:Math.max(1,...t.text.split('\n').map(line=>ctx.measureText(line).width)),h:t.text.split('\n').length*t.size*1.2};}
    function draw() {
        if(!image)return;
        const display=StudioPreview.display()||image,geometry=StudioPreview.metadata()?.geometry,viewWidth=geometry?.width||image.width,viewHeight=geometry?.height||image.height;
        const displayScale=Math.min(1,4096/Math.max(display.width,display.height));
        canvas.width=Math.max(1,Math.round(display.width*displayScale));canvas.height=Math.max(1,Math.round(display.height*displayScale));ctx.save();if(mirrored){ctx.translate(canvas.width,0);ctx.scale(-1,1);}ctx.drawImage(display,0,0,canvas.width,canvas.height);ctx.save();ctx.scale(canvas.width/viewWidth,canvas.height/viewHeight);if(geometry)ctx.translate(geometry.offsetX||0,geometry.offsetY||0);if(!geometry?.flatten)paintText(ctx);
        const t=texts.find(t=>t.id===selected);if(t){const b=textBounds(t);ctx.strokeStyle='#008dff';ctx.lineWidth=Math.max(1,image.width/(canvas.clientWidth||image.width));ctx.strokeRect(t.x-2,t.y-2,b.w+4,b.h+4);}
        ctx.restore();
        if(measurement){const sx=canvas.width/image.width,sy=canvas.height/image.height;ctx.strokeStyle='#fbbf24';ctx.lineWidth=2;ctx.beginPath();ctx.moveTo(measurement.start.x*sx,measurement.start.y*sy);ctx.lineTo(measurement.end.x*sx,measurement.end.y*sy);ctx.stroke();}
        ctx.restore();
        const zoom=$('zoom').value;const scale=zoom==='fit'?Math.min(1,Math.max(80,$('stage').clientWidth-60)/viewWidth,Math.max(80,$('stage').clientHeight-60)/viewHeight):+zoom;
        canvas.style.width=(viewWidth*scale)+'px';canvas.style.height=(viewHeight*scale)+'px';canvas.dataset.backdrop=$('backdrop').value;
    }
    function textInputs() {const t=texts.find(t=>t.id===selected);$('text-properties').hidden=!t;if(!t)return;for(const [id,key] of Object.entries({'text':'text','font':'font','font-size':'size','text-color':'color','weight':'weight','text-x':'x','text-y':'y'}))$(id).value=t[key];}
    function sync() {
        $('empty').hidden=!!image;canvas.hidden=!image;$('filename').textContent=filename||'Untitled artwork';buttons();
        $('layers').replaceChildren(new Option('Image only',''));for(const t of texts)$('layers').add(new Option(t.text.slice(0,35)||'Empty text',t.id));$('layers').value=selected;textInputs();
        if(!image){
            $('dimensions').textContent='Open an image to begin';
            $('print-info').textContent='Image dimensions, transparency, and output size appear here.';sizeKey=null;preflight?.update();
            return;
        }
        $('dimensions').textContent=`${image.width.toLocaleString()} × ${image.height.toLocaleString()} px`;
        $('crop-x').value=0;$('crop-y').value=0;$('crop-w').value=image.width;$('crop-h').value=image.height;
        const dpi=+$('dpi').value;const nextSize=image.width+'x'+image.height;if(sizeKey!==nextSize){$('print-width').value=(image.width/dpi).toFixed(4);$('print-height').value=(image.height/dpi).toFixed(4);sizeKey=nextSize;}resizeInfo();
        $('print-info').textContent=`${image.width.toLocaleString()} × ${image.height.toLocaleString()} px · ${(image.width/dpi).toFixed(2)} × ${(image.height/dpi).toFixed(2)} in · ${dpi} DPI`;
        const outside=texts.some(t=>{const b=textBounds(t);return t.x<0||t.y<0||t.x+b.w>image.width||t.y+b.h>image.height;});
        if(outside)$('print-info').textContent+=' A text layer extends beyond the canvas and will be clipped on export.';draw();preflight?.update();
    }
    async function openFiles(files) {
        if(busy||!files.length)return;
        if(files.length!==1)return message('Please open one image at a time.',true);
        const file=files[0];if(!['image/png','image/jpeg','image/webp'].includes(file.type))return message('Choose a PNG, JPG, or WebP image.',true);
        if(file.size>500*1024*1024)return message('Choose an image no larger than 500 MB.',true);
        if(dirty&&!confirm('Replace this image? Export first to keep your edits.'))return;
        busy=true;buttons();message('Opening image…');
        try {await PrintProgress.start('Opening image…');const decoded=await loadImage(file);dimensionsOK(decoded.naturalWidth,decoded.naturalHeight);const next=drawImageToCanvas(decoded);let dpi=null;try{dpi=readDpi(await file.arrayBuffer(),file.type);}catch{}measureMode=false;measurement=null;$('measure').setAttribute('aria-pressed','false');$('measurement').hidden=true;sizeKey=null;image=next;original=next;sourceDpi=dpi;filename=file.name;texts=[];selected='';undo=[];redo=[];dirty=false;halftoneUI?.documentChanged();production?.documentChanged();$('zoom').value='fit';sync();message('Image loaded unchanged. Choose a tool to begin.');}
        catch(e){message(e.message,true);}finally{busy=false;buttons();PrintProgress.finish();}
    }
    $('open').onclick=$('open-empty').onclick=()=>$('file').click();$('file').onchange=async()=>{await openFiles([...$('file').files]);$('file').value='';};
    for(const event of ['dragover','drop'])$('stage').addEventListener(event,e=>e.preventDefault());
    $('stage').ondragover=()=>$('stage').classList.add('over');$('stage').ondragleave=()=>$('stage').classList.remove('over');$('stage').ondrop=e=>{$('stage').classList.remove('over');openFiles([...e.dataTransfer.files]);};
    $('undo').onclick=()=>{if(!undo.length||busy)return;redo.push(snapshot());limitHistory(redo);restore(undo.pop());dirty=true;message('Undid last edit.');};
    $('redo').onclick=()=>{if(!redo.length||busy)return;undo.push(snapshot());limitHistory(undo);restore(redo.pop());dirty=true;message('Redid edit.');};
    $('clear-image').onclick=()=>{
        if(!image||busy)return;
        if(dirty&&!confirm('Clear this image and its edits? Export first to keep your work.'))return;
        StudioPreview.cancelAll();
        measureMode=false;measurement=null;mirrored=false;$('measure').setAttribute('aria-pressed','false');$('measurement').hidden=true;$('mirror-preview').checked=false;$('mirror-notice').hidden=true;exportUI?.close();
        image=null;original=null;texts=[];selected='';filename='';sourceDpi=null;
        undo=[];redo=[];dirty=false;drag=null;nextLayer=1;
        // Release the displayed pixels and document history; this does not delete the source file.
        canvas.width=1;canvas.height=1;canvas.style.width='';canvas.style.height='';
        $('file').value='';$('zoom').value='fit';$('backdrop').value='checker';$('stage').classList.remove('over');
        document.querySelectorAll('#image-tools input, #image-tools select, #text-tools input, #text-tools textarea, #text-tools select').forEach(field=>{
            if(field.closest('#halftone-panel,.production-tool'))return;
            if(field.tagName==='SELECT')field.selectedIndex=Math.max(0,[...field.options].findIndex(option=>option.defaultSelected));
            else field.value=field.defaultValue;
        });
        sync();message('Image cleared. Open another image to begin.');$('open-empty').focus();
    };
    $('reset').onclick=()=>edit('Image reset',()=>{image=original;texts=[];selected='';});
    function cropImage(x,y,w,h) {if(![x,y,w,h].every(Number.isInteger)||x<0||y<0||w<1||h<1||x+w>image.width||y+h>image.height)throw new Error('Crop must stay inside the image. Enter whole pixels.');const out=makeCanvas(w,h);out.getContext('2d').drawImage(image,x,y,w,h,0,0,w,h);image=out;texts=texts.map(t=>({...t,x:t.x-x,y:t.y-y}));}
    $('crop').onclick=()=>edit('Image cropped',()=>cropImage(+$('crop-x').value,+$('crop-y').value,+$('crop-w').value,+$('crop-h').value));
    $('center-artwork').onclick=()=>edit('Artwork centered',async()=>{await preflight.ready();const job=new StudioPreview.Job(),result=await job.run(image,'center',{}, {documentWidth:image.width,documentHeight:image.height,bounds:preflight.get().analysis?.bounds});const raster=result.bitmap||result.canvas;const out=makeCanvas(image.width,image.height);out.getContext('2d').drawImage(raster,0,0);raster.close?.();image=out;texts=texts.map(t=>({...t,x:t.x+result.geometry.offsetX,y:t.y+result.geometry.offsetY}));});
    $('trim').onclick=()=>edit('Artwork trimmed with padding',async()=>{await preflight.ready();const b=preflight.get().analysis?.bounds,pad=+$('trim-padding').value;if(!b)throw new Error('No visible artwork to trim.');if(!Number.isInteger(pad)||pad<0)throw new Error('Enter non-negative whole-pixel padding.');const out=makeCanvas(b.width+pad*2,b.height+pad*2);out.getContext('2d').drawImage(image,pad-b.x,pad-b.y);image=out;texts=texts.map(t=>({...t,x:t.x+pad-b.x,y:t.y+pad-b.y}));});
    async function resize(w,h) {
        dimensionsOK(w,h);
        const sx=w/image.width,sy=h/image.height;
        // Non-proportional resizing must transform text exactly with the artwork.
        const flatten=Math.abs(sx-sy)>0.002, source=flatten?composite():image;
        PrintProgress.update(`Resizing artwork to ${w.toLocaleString()} × ${h.toLocaleString()} pixels…`);
        const job=new StudioPreview.Job(),result=await job.run(source,'resize',{width:w,height:h});
        const raster=result.bitmap||result.canvas,out=makeCanvas(w,h);
        out.getContext('2d').drawImage(raster,0,0);raster.close?.();
        image=out;
        if(flatten){texts=[];selected='';}else texts=texts.map(t=>({...t,x:t.x*sx,y:t.y*sy,size:t.size*sx}));
    }
    function resizeTarget() {
        const dpi=+$('dpi').value,w=Math.round(+$('print-width').value*dpi);
        return [w,$('lock-ratio').checked?Math.round(w*image.height/image.width):Math.round(+$('print-height').value*dpi)];
    }
    function resizeInfo() {
        if(!image)return;
        const [w,h]=resizeTarget();
        $('print-recommendations').textContent='Recommended maximum sizes: '+[300,200,150].map(d=>`${d} DPI: ${(image.width/d).toFixed(2)} × ${(image.height/d).toFixed(2)} in`).join(' · ');preflight?.refresh();
        $('resize-info').textContent=`Output: ${w.toLocaleString()} × ${h.toLocaleString()} px · ${(w*h/1e6).toFixed(1)} MP. Current artwork provides ${Math.round(image.width/(w/(+$('dpi').value)))} pixels per print inch before resizing.`;
    }
    for(const factor of [2,4])$('upscale'+factor).onclick=()=>edit(`Image upscaled ${factor}×`,()=>resize(image.width*factor,image.height*factor));
    for(const side of ['width','height'])$('print-'+side).oninput=()=>{
        if(!image)return;
        if($('lock-ratio').checked){const other=side==='width'?'height':'width';$('print-'+other).value=(+$('print-'+side).value*(side==='width'?image.height/image.width:image.width/image.height)).toFixed(4);}
        resizeInfo();
    };
    $('lock-ratio').onchange=()=>$('print-width').oninput();
    $('resize').onclick=()=>edit('Image resized',()=>resize(...resizeTarget()));
    $('upscale-print').onclick=()=>edit('Image resampled for 300 DPI',async()=>{const w=Math.round(+$('print-width').value*300),h=Math.round(+$('print-height').value*300);await resize(w,h);$('dpi').value='300';sizeKey=null;});
    $('dpi').onchange=()=>{const w=$('print-width').value,h=$('print-height').value;sync();$('print-width').value=w;$('print-height').value=h;resizeInfo();halftoneUI?.dpiChanged();message('DPI updated. Apply resize to use these inches, or export to change DPI without resampling.');};
    $('rotate').onclick=()=>edit('Image rotated (text flattened)',()=>{const flat=composite(),out=makeCanvas(flat.height,flat.width),c=out.getContext('2d');c.translate(out.width,0);c.rotate(Math.PI/2);c.drawImage(flat,0,0);image=out;texts=[];selected='';});
    $('flip').onclick=()=>edit(mirrored?'Image flipped (text flattened). Mirror export is also enabled: the export will flip this again':'Image flipped (text flattened)',()=>{const flat=composite(),out=makeCanvas(flat.width,flat.height),c=out.getContext('2d');c.translate(out.width,0);c.scale(-1,1);c.drawImage(flat,0,0);image=out;texts=[];selected='';});
    const rgb=hex=>[1,3,5].map(i=>parseInt(hex.slice(i,i+2),16));
    $('replace-color').onclick=()=>edit('Matching colors replaced',()=>{const from=rgb($('from-color').value),to=rgb($('to-color').value),out=cloneCanvas(image),c=out.getContext('2d'),d=c.getImageData(0,0,out.width,out.height),tolerance=+$('color-tolerance').value;for(let i=0;i<d.data.length;i+=4)if(d.data[i+3]&&Math.hypot(d.data[i]-from[0],d.data[i+1]-from[1],d.data[i+2]-from[2])<=tolerance){d.data[i]=to[0];d.data[i+1]=to[1];d.data[i+2]=to[2];}c.putImageData(d,0,0);image=out;});
    $('add-text').onclick=()=>edit('Text layer added',()=>{const t={id:'text-'+nextLayer++,text:'Your text',font:'Arial',size:Math.max(12,Math.round(image.width/12)),weight:'700',color:'#000000',x:Math.round(image.width*.1),y:Math.round(image.height*.1)};texts.push(t);selected=t.id;});
    let fontCount=0;
    $('font-file').onchange=async()=>{
        const file=$('font-file').files[0];if(!file||busy)return;
        busy=true;buttons();
        try{
            await PrintProgress.start('Loading font…');
            if(file.size>10*1024*1024)throw new Error('Choose a font smaller than 10 MB.');
            const family='StudioFont'+(++fontCount),font=new FontFace(family,await file.arrayBuffer());
            await font.load();document.fonts.add(font);$('font').add(new Option(file.name,family));
            message('Font loaded. Select a text layer and choose it from the Font menu.');
        }catch(e){message('Could not load font. '+e.message,true);}finally{busy=false;buttons();PrintProgress.finish();$('font-file').value='';}
    };
    $('layers').onchange=()=>{selected=$('layers').value;textInputs();draw();};
    const fields={'text':'text','font':'font','font-size':'size','text-color':'color','weight':'weight','text-x':'x','text-y':'y'};
    for(const [id,key] of Object.entries(fields))$(id).onchange=()=>{if(busy)return;const t=texts.find(t=>t.id===selected);if(!t)return;let value=$(id).value;if(['size','x','y'].includes(key)){value=+value;if(!Number.isFinite(value)||(key==='size'&&(value<6||value>2000)))return message('Enter a valid text size or position.',true);}checkpoint();t[key]=value;sync();message('Text updated.');};
    $('delete-text').onclick=()=>edit('Text layer deleted',()=>{texts=texts.filter(t=>t.id!==selected);selected='';});
    function point(e){const r=canvas.getBoundingClientRect(),x=(e.clientX-r.left)*image.width/r.width;return {x:mirrored?image.width-x:x,y:(e.clientY-r.top)*image.height/r.height};}
    canvas.onpointerdown=e=>{if(!image||busy)return;if(production?.pick(point(e)))return;if(measureMode){StudioPreview.cancelAll();const p=point(e);measurement={start:p,end:p,dragging:true};canvas.setPointerCapture(e.pointerId);showMeasurement();return;}StudioPreview.cancelAll(true);const p=point(e),t=[...texts].reverse().find(t=>{const b=textBounds(t);return p.x>=t.x&&p.y>=t.y&&p.x<=t.x+b.w&&p.y<=t.y+b.h;});if(!t){selected='';sync();return;}selected=t.id;drag={id:t.id,x:p.x,y:p.y,tx:t.x,ty:t.y,before:snapshot()};canvas.setPointerCapture(e.pointerId);textInputs();$('layers').value=selected;draw();};
    canvas.onpointermove=e=>{if(measurement?.dragging){measurement.end=point(e);showMeasurement();return;}if(!drag)return;const p=point(e),t=texts.find(t=>t.id===drag.id);t.x=Math.round(drag.tx+p.x-drag.x);t.y=Math.round(drag.ty+p.y-drag.y);textInputs();draw();};
    canvas.onpointerup=()=>{if(measurement?.dragging){measurement.dragging=false;return;}if(!drag)return;undo.push(drag.before);limitHistory(undo);redo=[];dirty=true;drag=null;buttons();preflight?.update();};canvas.onpointercancel=()=>{if(measurement)measurement.dragging=false;if(drag){restore(drag.before);drag=null;}};
    $('zoom').onchange=$('backdrop').onchange=draw;window.addEventListener('resize',draw);
    function showMeasurement(){if(!measurement)return;const w=Math.abs(measurement.end.x-measurement.start.x),h=Math.abs(measurement.end.y-measurement.start.y),dpi=+$('dpi').value;$('measurement').hidden=false;$('measurement').textContent=`Width: ${(w/dpi).toFixed(2)} in / ${Math.round(w)} px · Height: ${(h/dpi).toFixed(2)} in / ${Math.round(h)} px · Distance: ${(Math.hypot(w,h)/dpi).toFixed(2)} in. Escape exits Measure.`;draw();}
    $('measure').onclick=()=>{StudioPreview.cancelAll();measureMode=!measureMode;measurement=null;$('measure').setAttribute('aria-pressed',String(measureMode));$('measurement').hidden=!measureMode;$('measurement').textContent='Drag across the artwork to measure. Escape exits.';draw();};
    function setMirror(value){mirrored=value;$('mirror-preview').checked=value;$('mirror-notice').hidden=!value;draw();}
    $('mirror-preview').onchange=()=>setMirror($('mirror-preview').checked);
    async function exportDocument(g){if(!image||busy)return;busy=true;buttons();message('Preparing print PNG…');try{await PrintProgress.start('Preparing print PNG…');const flat=texts.length?composite():image;let out=flat;if(g.trim||g.pad||g.mirror){out=makeCanvas(g.outputWidth,g.outputHeight);const c=out.getContext('2d');if(g.mirror){c.translate(out.width,0);c.scale(-1,1);}c.drawImage(flat,g.x,g.y,g.width,g.height,g.pad,g.pad,g.width,g.height);}PrintProgress.update('Encoding print PNG…');await PrintProgress.paint();const blob=await canvasToPngBlob(out),output=await addPngDpi(blob,Math.round(+$('dpi').value*METERS_PER_INCH));downloadBlob(output,makeOutputName(filename).replace('-clean-300dpi.png','-studio-'+$('dpi').value+'dpi'+(g.mirror?'-mirrored':'')+'.png'));dirty=false;message(`Exported ${out.width} × ${out.height} PNG at ${$('dpi').value} DPI${g.mirror?' (mirrored for transfer)':''}.`);}catch(error){message(error.message,true);}finally{busy=false;buttons();PrintProgress.finish();}}
    $('send-print').onclick=async()=>{
        if(!image||busy||!PrintOrderSender.ready())return;
        busy=true;buttons();StudioPreview.cancelAll();
        try {
            PrintOrderSender.validate(image.width,image.height);
            await PrintProgress.start('Preparing artwork for CSW…');
            let out=texts.length?composite():image;
            if(mirrored){const flipped=makeCanvas(out.width,out.height),c=flipped.getContext('2d');c.translate(out.width,0);c.scale(-1,1);c.drawImage(out,0,0);out=flipped;}
            const dpi=+$('dpi').value;
            PrintProgress.update('Encoding print PNG…');await PrintProgress.paint();
            const blob=await addPngDpi(await canvasToPngBlob(out),Math.round(dpi*METERS_PER_INCH));
            const receipt=await PrintOrderSender.send({blob,filename:makeOutputName(filename).replace('-clean-300dpi.png','-studio-'+dpi+'dpi'+(mirrored?'-mirrored':'')+'.png'),tool:'image-studio',dpi,width:out.width,height:out.height});
            message(receipt?'Artwork sent to CSW.':'Print order canceled.');
        } catch(error) { message(error.message,true);PrintOrderSender.feedback(error.message,true); }
        finally {busy=false;buttons();PrintProgress.finish();}
    };
    $('export').onclick=()=>exportUI.open();
    halftoneUI=createHalftoneUI({source:()=>image,dpi:()=>+$('dpi').value,busy:()=>busy,draw,notify:message,commit:operation=>edit('Halftone applied',async()=>{image=await operation();})});
    createLegacyStudioPreviews({source:()=>image,busy:()=>busy,draw,notify:message,commit:(label,operation)=>edit(label,async()=>{image=(await operation()).canvas;})});
    production=StudioTools.create({source:()=>image,sourceFor:(name,settings)=>name==='crop'&&settings.angle?composite():image,previewSource:(name,settings)=>{if(name!=='crop'||!settings.angle||!image)return null;const scale=Math.min(1,1000/Math.max(image.width,image.height)),out=makeCanvas(Math.max(1,Math.round(image.width*scale)),Math.max(1,Math.round(image.height*scale))),c=out.getContext('2d');c.scale(out.width/image.width,out.height/image.height);c.drawImage(image,0,0);paintText(c);return out;},options:()=>({dpi:+$('dpi').value,bounds:preflight?.get().analysis?.bounds}),busy:()=>busy,draw,notify:message,commit:(label,operation)=>edit(label,async()=>{const result=await operation();image=result.canvas;if(result.geometry?.flatten){texts=[];selected='';}else if(result.geometry){texts=texts.map(t=>({...t,x:t.x+(result.geometry.offsetX||0),y:t.y+(result.geometry.offsetY||0)}));}})});
    window.addEventListener('keydown',e=>{if(e.key==='Escape'){production?.cancelPicker();measureMode=false;measurement=null;$('measure').setAttribute('aria-pressed','false');$('measurement').hidden=true;draw();}});
    function openTool(id){const el=$(id);if(!el)return;for(let node=el;node;node=node.parentElement)if(node.tagName==='DETAILS')node.open=true;el.scrollIntoView({behavior:'smooth',block:'nearest'});}
    preflight=StudioPreflight.create({source:()=>image,composite:()=>texts.length?composite():image,key:()=>image?imageId(image)+'|'+JSON.stringify(texts):'',metadata:()=>({printWidth:+$('print-width').value,printHeight:+$('print-height').value,dpi:+$('dpi').value,outsideText:texts.some(t=>{const b=textBounds(t);return t.x<0||t.y<0||t.x+b.w>image.width||t.y+b.h>image.height;})}),openTool});
    const imageIds=new WeakMap();let imageSerial=0;function imageId(c){if(!imageIds.has(c))imageIds.set(c,++imageSerial);return imageIds.get(c);}
    exportUI=createStudioExport({source:()=>image,dpi:()=>+$('dpi').value,busy:()=>busy,notify:message,cancelPreview:()=>StudioPreview.cancelAll(true),preflight:()=>preflight.ready(),mirror:setMirror,mirrored:()=>mirrored,export:exportDocument});
    window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});sync();
})();
