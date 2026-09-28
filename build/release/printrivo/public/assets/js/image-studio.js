'use strict';
(() => {
    const $ = id => document.getElementById(id);
    const MAX_PIXELS = 24_000_000, MAX_EDGE = 12000, HISTORY_BYTES = 96_000_000;
    let image = null, original = null, texts = [], selected = '', filename = '', sourceDpi = null;
    let undo = [], redo = [], busy = false, dirty = false, drag = null, nextLayer = 1;
    const canvas = $('canvas'), ctx = canvas.getContext('2d');
    function message(text, error = false) { $('status').textContent = text; $('status').classList.toggle('error', error); }
    function dimensionsOK(w,h) { if (!Number.isFinite(w*h) || w<1 || h<1 || w>MAX_EDGE || h>MAX_EDGE || w*h>MAX_PIXELS) throw new Error('Use dimensions up to 12,000 pixels per side and 24 megapixels total.'); }
    function makeCanvas(w,h) { dimensionsOK(w,h); const c=document.createElement('canvas'); c.width=w;c.height=h;return c; }
    function snapshot() { return {image, texts:structuredClone(texts), selected}; }
    function limitHistory(stack) { while(stack.length>12 || (stack.length>1 && stack.reduce((n,s)=>n+s.image.width*s.image.height*4,0)>HISTORY_BYTES)) stack.shift(); }
    function restore(s) { image=s.image;texts=structuredClone(s.texts);selected=s.selected; sync(); }
    function checkpoint() { undo.push(snapshot());limitHistory(undo);redo=[];dirty=true; }
    async function edit(label, operation) {
        if (!image || busy) return;
        busy=true;buttons();message(label+'…');
        const before=snapshot();
        try { await new Promise(r=>requestAnimationFrame(()=>setTimeout(r,0))); await operation();undo.push(before);limitHistory(undo);redo=[];dirty=true;sync();message(label+'.'); }
        catch(e) { restore(before);message(e.message || 'The edit could not be completed.',true); }
        finally { busy=false;buttons(); }
    }
    function buttons() { $('image-tools').disabled=!image||busy;$('text-tools').disabled=!image||busy;$('export').disabled=!image||busy;$('reset').disabled=!image||busy;$('clear-image').disabled=!image||busy;$('undo').disabled=!undo.length||busy;$('redo').disabled=!redo.length||busy;$('open').disabled=busy;$('open-empty').disabled=busy; }
    function composite() { const out=cloneCanvas(image);paintText(out.getContext('2d'));return out; }
    function paintText(c) { for(const t of texts) {c.font=`${t.weight} ${t.size}px ${t.font}`;c.fillStyle=t.color;c.textBaseline='top';t.text.split('\n').forEach((line,i)=>c.fillText(line,t.x,t.y+i*t.size*1.2));} }
    function textBounds(t) {ctx.font=`${t.weight} ${t.size}px ${t.font}`;return {w:Math.max(1,...t.text.split('\n').map(line=>ctx.measureText(line).width)),h:t.text.split('\n').length*t.size*1.2};}
    function draw() {
        if(!image)return;
        canvas.width=image.width;canvas.height=image.height;ctx.drawImage(image,0,0);paintText(ctx);
        const t=texts.find(t=>t.id===selected);if(t){const b=textBounds(t);ctx.strokeStyle='#008dff';ctx.lineWidth=Math.max(1,canvas.width/(canvas.clientWidth||canvas.width));ctx.strokeRect(t.x-2,t.y-2,b.w+4,b.h+4);}
        const zoom=$('zoom').value;const scale=zoom==='fit'?Math.min(1,Math.max(80,$('stage').clientWidth-60)/image.width,Math.max(80,$('stage').clientHeight-60)/image.height):+zoom;
        canvas.style.width=(image.width*scale)+'px';canvas.style.height=(image.height*scale)+'px';canvas.dataset.backdrop=$('backdrop').value;
    }
    function textInputs() {const t=texts.find(t=>t.id===selected);$('text-properties').hidden=!t;if(!t)return;for(const [id,key] of Object.entries({'text':'text','font':'font','font-size':'size','text-color':'color','weight':'weight','text-x':'x','text-y':'y'}))$(id).value=t[key];}
    function sync() {
        $('empty').hidden=!!image;canvas.hidden=!image;$('filename').textContent=filename||'Untitled artwork';buttons();
        $('layers').replaceChildren(new Option('Image only',''));for(const t of texts)$('layers').add(new Option(t.text.slice(0,35)||'Empty text',t.id));$('layers').value=selected;textInputs();
        if(!image){
            $('dimensions').textContent='Open an image to begin';
            $('print-info').textContent='Image dimensions, transparency, and output size appear here.';
            return;
        }
        $('dimensions').textContent=`${image.width.toLocaleString()} × ${image.height.toLocaleString()} px`;
        $('crop-x').value=0;$('crop-y').value=0;$('crop-w').value=image.width;$('crop-h').value=image.height;
        const dpi=+$('dpi').value;$('print-width').value=(image.width/dpi).toFixed(2);$('print-height').value=(image.height/dpi).toFixed(2);
        $('print-info').textContent=`${image.width} × ${image.height} pixels · ${(image.width/dpi).toFixed(2)} × ${(image.height/dpi).toFixed(2)} inches at ${dpi} DPI. Source DPI: ${sourceDpi?Math.round(sourceDpi):'unknown'}. Background is retained unless you remove it.`;
        const outside=texts.some(t=>{const b=textBounds(t);return t.x<0||t.y<0||t.x+b.w>image.width||t.y+b.h>image.height;});
        if(outside)$('print-info').textContent+=' A text layer extends beyond the canvas and will be clipped on export.';draw();
    }
    async function openFiles(files) {
        if(busy||!files.length)return;
        if(files.length!==1)return message('Please open one image at a time.',true);
        const file=files[0];if(!['image/png','image/jpeg','image/webp'].includes(file.type))return message('Choose a PNG, JPG, or WebP image.',true);
        if(file.size>40*1024*1024)return message('Choose an image smaller than 40 MB.',true);
        if(dirty&&!confirm('Replace this image? Export first to keep your edits.'))return;
        busy=true;buttons();message('Opening image…');
        try {const decoded=await loadImage(file);dimensionsOK(decoded.naturalWidth,decoded.naturalHeight);const next=drawImageToCanvas(decoded);let dpi=null;try{dpi=readDpi(await file.arrayBuffer(),file.type);}catch{}image=next;original=next;sourceDpi=dpi;filename=file.name;texts=[];selected='';undo=[];redo=[];dirty=false;$('zoom').value='fit';sync();message('Image loaded unchanged. Choose a tool to begin.');}
        catch(e){message(e.message,true);}finally{busy=false;buttons();}
    }
    $('open').onclick=$('open-empty').onclick=()=>$('file').click();$('file').onchange=async()=>{await openFiles([...$('file').files]);$('file').value='';};
    for(const event of ['dragover','drop'])$('stage').addEventListener(event,e=>e.preventDefault());
    $('stage').ondragover=()=>$('stage').classList.add('over');$('stage').ondragleave=()=>$('stage').classList.remove('over');$('stage').ondrop=e=>{$('stage').classList.remove('over');openFiles([...e.dataTransfer.files]);};
    $('undo').onclick=()=>{if(!undo.length||busy)return;redo.push(snapshot());limitHistory(redo);restore(undo.pop());dirty=true;message('Undid last edit.');};
    $('redo').onclick=()=>{if(!redo.length||busy)return;undo.push(snapshot());limitHistory(undo);restore(redo.pop());dirty=true;message('Redid edit.');};
    $('clear-image').onclick=()=>{
        if(!image||busy)return;
        if(dirty&&!confirm('Clear this image and its edits? Export first to keep your work.'))return;
        image=null;original=null;texts=[];selected='';filename='';sourceDpi=null;
        undo=[];redo=[];dirty=false;drag=null;nextLayer=1;
        // Release the displayed pixels and document history; this does not delete the source file.
        canvas.width=1;canvas.height=1;canvas.style.width='';canvas.style.height='';
        $('file').value='';$('zoom').value='fit';$('backdrop').value='checker';$('stage').classList.remove('over');
        document.querySelectorAll('#image-tools input, #image-tools select, #text-tools input, #text-tools textarea, #text-tools select').forEach(field=>{
            if(field.tagName==='SELECT')field.selectedIndex=Math.max(0,[...field.options].findIndex(option=>option.defaultSelected));
            else field.value=field.defaultValue;
        });
        sync();message('Image cleared. Open another image to begin.');$('open-empty').focus();
    };
    $('reset').onclick=()=>edit('Image reset',()=>{image=original;texts=[];selected='';});
    $('remove-bg').onclick=()=>edit('Background removed',()=>{const d=image.getContext('2d').getImageData(0,0,image.width,image.height);const cleaned=removeBackground(d,sampleCornerColor(d,image.width,image.height),+$('tolerance').value);const out=makeCanvas(image.width,image.height);out.getContext('2d').putImageData(cleaned,0,0);image=out;});
    function cropImage(x,y,w,h) {if(![x,y,w,h].every(Number.isInteger)||x<0||y<0||w<1||h<1||x+w>image.width||y+h>image.height)throw new Error('Crop must stay inside the image. Enter whole pixels.');const out=makeCanvas(w,h);out.getContext('2d').drawImage(image,x,y,w,h,0,0,w,h);image=out;texts=texts.map(t=>({...t,x:t.x-x,y:t.y-y}));}
    $('crop').onclick=()=>edit('Image cropped',()=>cropImage(+$('crop-x').value,+$('crop-y').value,+$('crop-w').value,+$('crop-h').value));
    $('trim').onclick=()=>edit('Transparent margins trimmed',()=>{const flat=composite(),d=flat.getContext('2d').getImageData(0,0,flat.width,flat.height),b=findAlphaBounds(d,flat.width,flat.height);cropImage(b.x,b.y,b.width,b.height);});
    function resize(w,h) {dimensionsOK(w,h);const sx=w/image.width,sy=h/image.height;const out=makeCanvas(w,h),c=out.getContext('2d');c.imageSmoothingEnabled=true;c.imageSmoothingQuality='high';c.drawImage(image,0,0,w,h);image=out;texts=texts.map(t=>({...t,x:t.x*sx,y:t.y*sy,size:t.size*sx}));}
    for(const factor of [2,4])$('upscale'+factor).onclick=()=>edit(`Image upscaled ${factor}×`,()=>resize(image.width*factor,image.height*factor));
    $('print-width').oninput=()=>{if(image)$('print-height').value=(+$('print-width').value*image.height/image.width).toFixed(2);};$('print-height').oninput=()=>{if(image)$('print-width').value=(+$('print-height').value*image.width/image.height).toFixed(2);};
    $('resize').onclick=()=>edit('Print dimensions applied',()=>{const w=Math.round(+$('print-width').value*+$('dpi').value);resize(w,Math.round(w*image.height/image.width));});$('dpi').onchange=()=>{sync();message('Export DPI updated. Pixel dimensions are unchanged.');};
    $('rotate').onclick=()=>edit('Image rotated (text flattened)',()=>{const flat=composite(),out=makeCanvas(flat.height,flat.width),c=out.getContext('2d');c.translate(out.width,0);c.rotate(Math.PI/2);c.drawImage(flat,0,0);image=out;texts=[];selected='';});
    $('flip').onclick=()=>edit('Image flipped (text flattened)',()=>{const flat=composite(),out=makeCanvas(flat.width,flat.height),c=out.getContext('2d');c.translate(out.width,0);c.scale(-1,1);c.drawImage(flat,0,0);image=out;texts=[];selected='';});
    $('colors').onclick=()=>edit('Color adjustments applied',()=>{const out=cloneCanvas(image),c=out.getContext('2d'),d=c.getImageData(0,0,out.width,out.height),brightness=+$('brightness').value*2.55,contrast=Math.pow((100 + +$('contrast').value)/100,2),sat=+$('saturation').value/100;for(let i=0;i<d.data.length;i+=4){let r=d.data[i],g=d.data[i+1],b=d.data[i+2],l=.2126*r+.7152*g+.0722*b;for(const [j,v] of [r,g,b].entries())d.data[i+j]=((l+(v-l)*sat)-128)*contrast+128+brightness;}c.putImageData(d,0,0);image=out;$('brightness').value=0;$('contrast').value=0;$('saturation').value=100;});
    const rgb=hex=>[1,3,5].map(i=>parseInt(hex.slice(i,i+2),16));
    $('replace-color').onclick=()=>edit('Matching colors replaced',()=>{const from=rgb($('from-color').value),to=rgb($('to-color').value),out=cloneCanvas(image),c=out.getContext('2d'),d=c.getImageData(0,0,out.width,out.height),tolerance=+$('color-tolerance').value;for(let i=0;i<d.data.length;i+=4)if(d.data[i+3]&&Math.hypot(d.data[i]-from[0],d.data[i+1]-from[1],d.data[i+2]-from[2])<=tolerance){d.data[i]=to[0];d.data[i+1]=to[1];d.data[i+2]=to[2];}c.putImageData(d,0,0);image=out;});
    $('add-text').onclick=()=>edit('Text layer added',()=>{const t={id:'text-'+nextLayer++,text:'Your text',font:'Arial',size:Math.max(12,Math.round(image.width/12)),weight:'700',color:'#000000',x:Math.round(image.width*.1),y:Math.round(image.height*.1)};texts.push(t);selected=t.id;});
    $('layers').onchange=()=>{selected=$('layers').value;textInputs();draw();};
    const fields={'text':'text','font':'font','font-size':'size','text-color':'color','weight':'weight','text-x':'x','text-y':'y'};
    for(const [id,key] of Object.entries(fields))$(id).onchange=()=>{if(busy)return;const t=texts.find(t=>t.id===selected);if(!t)return;let value=$(id).value;if(['size','x','y'].includes(key)){value=+value;if(!Number.isFinite(value)||(key==='size'&&(value<6||value>2000)))return message('Enter a valid text size or position.',true);}checkpoint();t[key]=value;sync();message('Text updated.');};
    $('delete-text').onclick=()=>edit('Text layer deleted',()=>{texts=texts.filter(t=>t.id!==selected);selected='';});
    function point(e){const r=canvas.getBoundingClientRect();return {x:(e.clientX-r.left)*canvas.width/r.width,y:(e.clientY-r.top)*canvas.height/r.height};}
    canvas.onpointerdown=e=>{if(!image||busy)return;const p=point(e),t=[...texts].reverse().find(t=>{const b=textBounds(t);return p.x>=t.x&&p.y>=t.y&&p.x<=t.x+b.w&&p.y<=t.y+b.h;});if(!t){selected='';sync();return;}selected=t.id;drag={id:t.id,x:p.x,y:p.y,tx:t.x,ty:t.y,before:snapshot()};canvas.setPointerCapture(e.pointerId);textInputs();$('layers').value=selected;draw();};
    canvas.onpointermove=e=>{if(!drag)return;const p=point(e),t=texts.find(t=>t.id===drag.id);t.x=Math.round(drag.tx+p.x-drag.x);t.y=Math.round(drag.ty+p.y-drag.y);textInputs();draw();};
    canvas.onpointerup=()=>{if(!drag)return;undo.push(drag.before);limitHistory(undo);redo=[];dirty=true;drag=null;buttons();};canvas.onpointercancel=()=>{if(drag){restore(drag.before);drag=null;}};
    $('zoom').onchange=$('backdrop').onchange=draw;window.addEventListener('resize',draw);
    $('export').onclick=async()=>{if(!image||busy)return;busy=true;buttons();message('Preparing PNG…');try{const flat=composite(),blob=await canvasToPngBlob(flat),output=await addPngDpi(blob,Math.round(+$('dpi').value*METERS_PER_INCH));downloadBlob(output,makeOutputName(filename).replace('-clean-300dpi.png', '-studio-'+$('dpi').value+'dpi.png'));dirty=false;message(`Exported ${flat.width} × ${flat.height} PNG at ${$('dpi').value} DPI.`);}catch(e){message(e.message,true);}finally{busy=false;buttons();}};
    window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});sync();
})();
