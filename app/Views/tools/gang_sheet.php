<!doctype html>
<html lang="en">
<head>
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Printrivo Gang Sheet Builder | Printrivo</title>
  <link rel="icon" href="<?= base_url('assets/img/printrivo_light_logo.png') ?>">
  <style>
    :root{color-scheme:dark;--bg:#03060b;--panel:#090e17;--soft:#111a29;--line:#26344a;--blue:#8fbad3;--ink:#f7f9ff;--muted:#9aa9be;--good:#3ddc97;--warn:#ffca58;--danger:#ff6b6b}
    *{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 15% 0,#1d3557 0,transparent 28%),var(--bg);color:var(--ink);font:14px Arial,sans-serif}button,input,select,textarea{font:inherit}.top{height:72px;display:flex;align-items:center;gap:14px;padding:10px 18px;background:#05080e;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:20}.logo{width:48px;height:48px;object-fit:contain}.brand{margin-right:auto}.brand b{font-size:20px}.brand span{display:block;color:var(--muted);font-size:12px;margin-top:3px}.btn{border:1px solid var(--line);border-radius:8px;background:#111927;color:white;padding:10px 13px;cursor:pointer;text-decoration:none;font-weight:700}.btn:hover{border-color:#457b9d}.btn.primary{background:linear-gradient(#1d3557,#1d3557);border-color:#457b9d}.btn.danger{color:#ff9b9b}.layout{display:grid;grid-template-columns:280px minmax(560px,1fr) 300px;height:calc(100vh - 72px)}.sidebar{background:var(--panel);border-right:1px solid var(--line);padding:15px;overflow:auto}.sidebar.right{border-right:0;border-left:1px solid var(--line)}h2{font-size:14px;text-transform:uppercase;margin:4px 0 14px;color:#b7d4e5}.card{border:1px solid var(--line);background:var(--soft);border-radius:10px;padding:12px;margin-bottom:12px}.field{margin-bottom:12px}.field label{display:flex;justify-content:space-between;color:var(--muted);margin-bottom:6px;font-size:12px}.field input,.field select,.field textarea{width:100%;background:#060a11;border:1px solid var(--line);color:white;border-radius:7px;padding:9px}.row{display:grid;grid-template-columns:1fr 1fr;gap:8px}.upload{display:grid;place-items:center;text-align:center;min-height:112px;border:2px dashed #8fbad3;border-radius:10px;background:#0b1830;cursor:pointer;padding:14px}.upload:hover,.upload.over{border-color:white}.upload input{display:none}.upload b{font-size:16px}.upload small{color:var(--muted);margin-top:5px}.asset{display:grid;grid-template-columns:52px 1fr;gap:9px;align-items:center;border:1px solid var(--line);padding:7px;border-radius:8px;margin-top:8px;background:#080d15}.asset img{width:52px;height:52px;object-fit:contain;background:repeating-conic-gradient(#eee 0 25%,#ccc 0 50%) 50%/12px 12px}.asset b{font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.asset small{display:block;color:var(--muted);margin-top:4px}.stage-wrap{overflow:auto;padding:24px;display:flex;align-items:flex-start;justify-content:center;background:#172335}.sheet{position:relative;background:#d9dde2;box-shadow:0 12px 40px #000;border:1px solid #cad2df;flex:0 0 auto;overflow:hidden}.sheet:empty:after{content:'Upload artwork, then click Add to sheet';position:absolute;inset:0;display:grid;place-items:center;color:#778397;font-weight:700}.item{position:absolute;cursor:move;user-select:none;touch-action:none;transform-origin:center}.item img{width:100%;height:100%;object-fit:contain;pointer-events:none}.item.selected{outline:2px solid var(--blue);outline-offset:2px}.item.low{filter:drop-shadow(0 0 2px var(--danger))}.handle{display:none;position:absolute;width:13px;height:13px;right:-7px;bottom:-7px;border-radius:50%;background:white;border:3px solid var(--blue);cursor:nwse-resize}.selected .handle{display:block}.toolbar{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px}.toolbar .btn{padding:8px 10px}.metric{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--line);color:var(--muted)}.metric strong{color:white}.quality{padding:9px;border-radius:7px;background:#071a13;color:var(--good);font-weight:700}.quality.warn{background:#24190a;color:var(--warn)}.help{color:var(--muted);line-height:1.5;font-size:12px}.empty-props{color:var(--muted);line-height:1.5}.status{min-height:20px;color:var(--muted);font-size:12px;margin:9px 0}@media(max-width:1000px){.layout{grid-template-columns:230px minmax(500px,1fr)}.sidebar.right{grid-column:1/-1;border-left:0;border-top:1px solid var(--line)}body{overflow:auto}.layout{height:auto}.stage-wrap{height:680px}}
    .asset{grid-template-columns:52px minmax(0,1fr);align-items:start;padding:8px;overflow:hidden}.asset>div{min-width:0}.asset b{display:block;max-width:100%}.asset-dimensions{display:grid;gap:2px;margin-top:4px}.asset-dimensions small{margin:0}.asset-inch-size{color:#b7d4e5!important}.asset-controls{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;margin-top:7px}.asset-control{min-width:0}.asset-control label{display:block;color:var(--muted);font-size:10px;margin-bottom:3px;text-transform:uppercase}.asset-control input{display:block;width:100%;min-width:0;height:30px;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:#060a11;color:white}.add-sheet{display:block;width:100%;max-width:100%;margin-top:7px;padding:7px 8px!important;overflow:hidden}
    .width-banner{position:sticky;top:72px;z-index:19;padding:16px 22px;background:#721c24;color:#fff;border-bottom:3px solid #ff6b6b;font-size:16px;font-weight:bold;line-height:1.5;overflow-wrap:anywhere}.width-banner[hidden]{display:none}@media print{.width-banner{display:none!important}}
    .asset-flag{display:inline-block;margin-top:5px;padding:3px 6px;border-radius:4px;font-size:10px;font-weight:bold}.asset-flag.warning{color:#241400;background:var(--warn)}.asset-flag.ready{color:#002d1b;background:var(--good)}.stage-wrap.over{box-shadow:inset 0 0 0 4px var(--blue);background-color:#132747}.stage-wrap.over:after{content:'Drop artwork to add it to the sheet';position:fixed;left:50%;top:92px;transform:translateX(-50%);z-index:30;padding:12px 18px;border-radius:8px;background:#1d3557;color:white;font-size:16px;font-weight:bold;pointer-events:none}
  </style>
    <link rel="stylesheet" href="<?= base_url('assets/css/brand.css?v=20260918-mobile') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/report-bug.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/processing-progress.css?v=20260924-print') ?>">
<script src="<?= base_url('assets/js/processing-progress.js?v=20260924-print') ?>"></script>
</head>
<body class="gang-sheet-builder" data-mobile-panel="setup" data-upload-url="<?= site_url('api/tools/gang-sheet/inspect') ?>">
<header class="top"><img class="logo" src="<?= base_url('assets/img/printrivo_dark_logo.png') ?>" alt="Printrivo logo"><div class="brand"><b>Printrivo Gang Sheet Builder</b><span>Printrivo · DTF and UVDTF print preparation</span></div><a class="btn" href="<?= site_url('dashboard') ?>">Dashboard</a><?= view('partials/report_bug_button') ?><button class="btn primary" id="saveSheet">Download Print Sheet</button><?php if (auth()->user()?->inGroup('admin')): ?><button class="btn" id="sendPrint" title="Send one copy of this gang sheet to CSW using your account details">Send to printer</button><?php endif ?></header>
<div id="widthBanner" class="width-banner" role="alert" tabindex="-1" hidden></div>
<nav class="mobile-builder-nav" aria-label="Builder sections">
<button type="button" data-panel="setup" aria-pressed="true" aria-controls="builder-setup">1. Setup</button>
<button type="button" data-panel="canvas" aria-pressed="false" aria-controls="builder-canvas">2. Canvas</button>
<button type="button" data-panel="details" aria-pressed="false" aria-controls="builder-details">3. Edit &amp; details</button>
</nav>
<main class="layout">
  <aside class="sidebar" id="builder-setup">
    <h2>1. Sheet setup</h2>
    <div class="card"><div class="field"><label for="sheetWidth">Print width</label><select id="sheetWidth"><option value="16">16 inches</option><option value="22">22 inches</option></select></div><div class="field"><label for="sheetLength"><span>Sheet length</span><span id="sheetWidthLabel">16 in wide</span></label><input id="sheetLength" type="number" value="12" step="0.5" min="1" aria-label="Sheet length in inches"></div><div class="field"><label for="gap"><span>Artwork spacing</span><span>inches</span></label><input id="gap" type="number" min="0" max="2" step="0.05" value="0.25"></div><small style="color:var(--muted)">Length fits automatically when artwork is added or arranged. You can also edit it manually.</small></div>
    <h2>2. Artwork</h2>
    <label class="upload" id="drop"><input id="upload" type="file" accept="image/png,image/jpeg,image/webp,image/gif" multiple><b>Upload artwork</b><small>PNG recommended · tap to choose files or drag here</small></label><p class="status" id="status"></p><div id="uploadProgress" hidden style="margin:10px 0;overflow-wrap:anywhere"><div id="uploadProgressLabel" role="status" style="font-size:12px;margin-bottom:6px"></div><progress id="uploadProgressBar" max="100" value="0" aria-label="Artwork upload progress" style="width:100%;height:16px;accent-color:#8fbad3"></progress></div><div id="assets"></div>
  </aside>
  <section class="stage-wrap" id="builder-canvas" aria-label="Artwork canvas"><div class="sheet" id="sheet" tabindex="-1" aria-label="16 inch gang sheet canvas"></div></section>
  <aside class="sidebar right" id="builder-details">
    <h2>Selected artwork</h2>
    <div class="toolbar"><button class="btn" id="duplicate">Duplicate</button><button class="btn" id="rotate">Rotate 90°</button><button class="btn danger" id="remove">Remove</button></div>
    <div class="card" id="properties"><p class="empty-props">Select artwork on the sheet to change its size and position.</p></div>
    <button class="btn primary" id="autoBuild" style="width:100%;margin-bottom:14px">Auto Build / Arrange</button>
    <div class="toolbar"><button class="btn" id="centerArtwork">Center artwork</button><button class="btn" id="trimSheet">Trim sheet</button></div>
    <h2>Sheet details</h2>
    <div class="card sheet-details" aria-live="polite">
      <div class="metric"><span>Sheet size</span><strong id="sheetMetric"></strong></div>
      <div class="metric"><span>Sheet area</span><strong id="sheetArea"></strong></div>
      <div class="metric"><span>Artwork pieces</span><strong id="pieceCount">0</strong></div>
      <div class="metric"><span>Used area</span><strong id="usedArea"></strong></div>
      <div class="metric"><span>Remaining area</span><strong id="remainingArea"></strong></div>
      <div class="metric"><span>Sheet coverage</span><strong id="coverage">0%</strong></div>
      <div class="metric"><span>Export at 300 DPI</span><strong id="exportSize"></strong></div>
      <div class="metric"><span>Resolution check</span><strong id="qualityCount">Ready</strong></div>
      <p class="help">Area is based on artwork rectangles within the sheet, including transparent space. Overlapping areas count once.</p>
    </div>
    <div class="quality" id="quality">All placed artwork is print ready.</div><p class="help">Drag artwork to position it. Use the width and height inputs to resize. Adding artwork arranges the designs and fits the sheet length. You can also click Auto Build / Arrange. Artwork starts at its image print size; files without DPI metadata use 300 DPI. Original backgrounds and margins are preserved. Auto Build keeps your entered dimensions. Resize oversized artwork using the inputs before downloading.</p>
  </aside>
</main>
<dialog id="downloadResult" style="max-width:480px;width:calc(100% - 32px);background:#111a29;color:#f7f9ff;border:1px solid #26344a;border-radius:12px;padding:24px" aria-labelledby="downloadTitle">
  <h2 id="downloadTitle">Print sheet download</h2>
  <p id="downloadMessage" role="status" style="line-height:1.5;overflow-wrap:anywhere"></p>
  <a id="downloadFile" class="btn primary" hidden>Save PNG</a>
  <button type="button" class="btn" onclick="document.getElementById('downloadResult').close()">Back to sheet</button>
</dialog>
<?= view('partials/print_order') ?>
<script src="<?= base_url('assets/js/upload-progress.js') ?>"></script>
<script>
const assets=[],items=[];let PX=24,SHEET_W=16,selected=null,exporting=false,downloadUrl=null;
const $=s=>document.querySelector(s), sheet=$('#sheet'),stageWrap=$('.stage-wrap');
function sheetH(){return +$('#sheetLength').value}function renderSheet(){if(window.innerWidth<=760)PX=Math.max(6,Math.min(24,(window.innerWidth-40)/SHEET_W));else PX=24;sheet.setAttribute('aria-label',`${SHEET_W} inch gang sheet canvas`);$('#sheetWidthLabel').textContent=`${SHEET_W} in wide`;sheet.style.width=SHEET_W*PX+'px';sheet.style.height=sheetH()*PX+'px';items.forEach(position);renderDetails()}
function showBuilderPanel(panel){
    document.body.dataset.mobilePanel=panel;
    document.querySelectorAll('[data-panel]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.panel===panel)));
    if(panel==='canvas')renderSheet();
}
document.querySelectorAll('[data-panel]').forEach(button=>button.onclick=()=>showBuilderPanel(button.dataset.panel));
window.addEventListener?.('resize',()=>renderSheet());
function status(t,bad=false){$('#status').textContent=t;$('#status').style.color=bad?'var(--danger)':'var(--muted)'}
$('#upload').onchange=async e=>{const input=e.target;try{await addFiles([...input.files])}finally{input.value=''}};['dragover','drop'].forEach(n=>$('#drop').addEventListener(n,e=>e.preventDefault()));$('#drop').ondragover=()=>$('#drop').classList.add('over');$('#drop').ondragleave=()=>$('#drop').classList.remove('over');$('#drop').ondrop=e=>{$('#drop').classList.remove('over');addFiles([...e.dataTransfer.files])};
stageWrap.ondragover=e=>{e.preventDefault();stageWrap.classList.add('over')};stageWrap.ondragleave=e=>{if(!stageWrap.contains(e.relatedTarget))stageWrap.classList.remove('over')};stageWrap.ondrop=async e=>{e.preventDefault();stageWrap.classList.remove('over');const start=assets.length;await addFiles([...e.dataTransfer.files]);const added=assets.slice(start);for(const a of added){for(let i=0;i<a.qty;i++)makeItem(a)}if(added.length){autoArrangeItems();renderAssets();status(`${added.length} artwork file${added.length===1?'':'s'} uploaded and added to the sheet.`)}};
async function addFiles(files){files=files.filter(f=>f.type.startsWith('image/'));if(!files.length)return status('Choose image files.',true);if(!dtfProgress.start())return;status('Reading artwork dimensions…');const errors=[];let loaded=0;for(const file of files){dtfProgress.show(`Preparing ${file.name} (${files.indexOf(file)+1} of ${files.length})…`);await new Promise(resolve=>requestAnimationFrame(()=>setTimeout(resolve,0)));let source;try{source=await readArtworkSource(file);const {url,dim}=source,info=await imagePrintInfo(file);assets.push({id:crypto.randomUUID(),name:file.name,url,originalUrl:url,pxW:dim.w,pxH:dim.h,width:dim.w/info.dpi,height:dim.h/info.dpi,dpi:info.dpi,dpiMissing:info.dpiMissing,qty:1});loaded++;}catch(e){if(source?.url.startsWith('blob:'))URL.revokeObjectURL(source.url);errors.push(`${file.name}: ${e.message||'Image could not be read. Please re-export it as a PNG or JPG and try again.'}`)}}dtfProgress.finish(errors.length?`${loaded} of ${files.length} files ready. See errors below.`:`${loaded} files ready.`);renderAssets();if(errors.length)return status(`${loaded} file(s) loaded. ${errors.join(' ')}`,true);status(`${files.length} artwork file${files.length===1?'':'s'} ready. Original image preserved.`)}
async function imagePrintInfo(file){const local=await dtfProgress.pngInfo(file);if(local)return local;return dtfProgress.upload(file)}
function dimensions(url){return new Promise((res,rej)=>{const i=new Image;i.onload=()=>i.naturalWidth&&i.naturalHeight?res({w:i.naturalWidth,h:i.naturalHeight}):rej(new Error('The image has no readable dimensions.'));i.onerror=()=>rej(new Error('Your browser could not decode this image. Re-export it as a PNG or JPG and try again.'));i.src=url})}
async function readArtworkSource(file){
    if(!file.size)throw new Error('This file is empty. Choose the original image file.');
    const url=URL.createObjectURL(file);
    try{return {url,dim:await dimensions(url)}}
    catch(error){
        URL.revokeObjectURL(url);
        // Some browsers or page policies reject blob-backed image previews.
        // Retry the same file bytes as an embedded image without changing artwork.
        const embedded=await new Promise((resolve,reject)=>{
            const reader=new FileReader();
            reader.onload=()=>resolve(reader.result);
            reader.onerror=()=>reject(new Error('The file could not be read from your device. Save a local copy and try again.'));
            reader.onabort=()=>reject(new Error('Image reading was cancelled. Please choose the file again.'));
            reader.readAsDataURL(file);
        });
        return {url:embedded,dim:await dimensions(embedded)};
    }
}
function renderAssets(){$('#assets').innerHTML='';assets.forEach(a=>{const d=document.createElement('div');d.className='asset';d.innerHTML=`<img src="${a.url}" alt=""><div><b title="${esc(a.name)}">${esc(a.name)}</b><div class="asset-dimensions"><small>${a.pxW} × ${a.pxH} px</small><small class="asset-inch-size">${round(a.width)} × ${round(a.height)} in</small><small>${a.dpiMissing?'300 DPI assumed (no size metadata)':`${round(a.dpi)} DPI from image`}</small></div><div class="asset-controls"><div class="asset-control"><label>Width (in)</label><input aria-label="Width in inches" type="number" min=".1" step=".1" value="${round(a.width)}"></div><div class="asset-control"><label>Length (in)</label><input aria-label="Length in inches" type="number" min=".1" step=".1" value="${round(a.height)}"></div><div class="asset-control"><label>Qty</label><input aria-label="Quantity" type="number" min="1" step="1" value="${a.qty}"></div></div><button class="btn add-sheet">Add to sheet</button></div>`;const ins=d.querySelectorAll('input'),inchSize=d.querySelector('.asset-inch-size');ins[0].oninput=()=>{a.width=Math.max(.1,+ins[0].value||1);a.height=a.width*a.pxH/a.pxW;ins[1].value=round(a.height);inchSize.textContent=`${round(a.width)} × ${round(a.height)} in`};ins[1].oninput=()=>{a.height=Math.max(.1,+ins[1].value||1);a.width=a.height*a.pxW/a.pxH;ins[0].value=round(a.width);inchSize.textContent=`${round(a.width)} × ${round(a.height)} in`};ins[2].oninput=()=>a.qty=Math.max(1,Math.floor(+ins[2].value||1));d.querySelector('button').onclick=async()=>{for(let i=0;i<a.qty;i++)makeItem(a);autoArrangeItems();renderAssets();showBuilderPanel('canvas');status(`${a.qty} piece${a.qty===1?'':'s'} added at the entered size.`)};$('#assets').append(d)})}
function makeItem(a,x=.25,y=.25,w=a.width,h=a.height,rotation=0){const it={id:crypto.randomUUID(),asset:a,x,y,w,h,rotation};items.push(it);drawItem(it);select(it);return it}
function drawItem(it){let el=document.createElement('div');el.className='item';el.dataset.id=it.id;el.innerHTML=`<img src="${it.asset.url}" alt="${esc(it.asset.name)}">`;sheet.append(el);it.el=el;position(it);el.onpointerdown=e=>dragStart(e,it)}
function position(it){it.el.style.left=it.x*PX+'px';it.el.style.top=it.y*PX+'px';it.el.style.width=it.w*PX+'px';it.el.style.height=it.h*PX+'px';it.el.style.transform='';const rotated=it.rotation%180!==0,img=it.el.querySelector('img');img.style.position='absolute';img.style.width=(rotated?it.h:it.w)*PX+'px';img.style.height=(rotated?it.w:it.h)*PX+'px';img.style.left=(rotated?(it.w-it.h)/2:0)*PX+'px';img.style.top=(rotated?(it.h-it.w)/2:0)*PX+'px';img.style.transform=`rotate(${it.rotation}deg)`;const dpi=effectiveDpi(it);it.el.classList.toggle('low',dpi<250)}
function select(it){selected=it;document.querySelectorAll('.item').forEach(e=>e.classList.toggle('selected',e===it?.el));renderProps()}
function dragStart(e,it){if(e.target.classList.contains('handle'))return;e.preventDefault();sheet.focus();select(it);let sx=e.clientX,sy=e.clientY,ox=it.x,oy=it.y;function move(ev){it.x=ox+(ev.clientX-sx)/PX;it.y=oy+(ev.clientY-sy)/PX;position(it)}function up(){removeEventListener('pointermove',move);removeEventListener('pointerup',up);removeEventListener('pointercancel',up);renderDetails()}addEventListener('pointermove',move);addEventListener('pointerup',up);addEventListener('pointercancel',up)}
function renderProps(){let p=$('#properties');if(!selected){p.innerHTML='<p class="empty-props">Select artwork on the sheet to change its size and position.</p>';return}p.innerHTML=`<div class="row"><div class="field"><label>Width (in)</label><input id="pw" type="number" min=".1" step=".01" value="${round(selected.w)}"></div><div class="field"><label>Height (in)</label><input id="ph" type="number" min=".1" step=".01" value="${round(selected.h)}"></div></div><div class="row"><div class="field"><label>X position</label><input id="px" type="number" min="0" step=".01" value="${round(selected.x)}"></div><div class="field"><label>Y position</label><input id="py" type="number" min="0" step=".01" value="${round(selected.y)}"></div></div><small id="dpi"></small>`;let ratio=selected.rotation%180?selected.asset.pxW/selected.asset.pxH:selected.asset.pxH/selected.asset.pxW;$('#pw').oninput=()=>{selected.w=Math.max(.1,+$('#pw').value||.1);selected.h=selected.w*ratio;position(selected);renderProps();renderDetails()};$('#ph').oninput=()=>{selected.h=Math.max(.1,+$('#ph').value||.1);selected.w=selected.h/ratio;position(selected);renderProps();renderDetails()};$('#px').oninput=()=>{selected.x=+$('#px').value||0;position(selected);renderDetails()};$('#py').oninput=()=>{selected.y=+$('#py').value||0;position(selected);renderDetails()};let dpi=Math.round(effectiveDpi(selected));$('#dpi').textContent=`Effective resolution: ${dpi} DPI`;$('#dpi').style.color=dpi<250?'var(--danger)':'var(--good)'}
$('#duplicate').onclick=()=>{if(selected){makeItem(selected.asset,selected.x+.3,selected.y+.3,selected.w,selected.h,selected.rotation);renderDetails()}};$('#rotate').onclick=()=>{if(!selected)return;[selected.w,selected.h]=[selected.h,selected.w];selected.rotation=(selected.rotation+90)%360;position(selected);renderProps();renderDetails()};$('#remove').onclick=()=>{if(!selected)return;selected.el.remove();items.splice(items.indexOf(selected),1);select(null);renderDetails()};
function deleteSelectedArtwork(event){
    if(event.key!=='Delete'||event.defaultPrevented||event.isComposing||event.ctrlKey||event.metaKey||event.altKey||exporting||!selected)return;
    const target=event.target;
    if(target?.isContentEditable||target?.closest?.('input,textarea,select,[role="textbox"],dialog')||document.querySelector('dialog[open]'))return;
    event.preventDefault();$('#remove').onclick();
}
document.addEventListener?.('keydown',deleteSelectedArtwork);

function autoArrangeItems(){
    const gap=Math.min(2,Math.max(0,+$('#gap').value||0)),available=SHEET_W-2*gap;
    let x=gap,y=gap,rowH=0,resized=0;
    items.forEach(it=>{
        if(it.w>available)resized++; // Report oversized artwork without changing its dimensions.
        if(x+it.w>SHEET_W-gap+0.000001){x=gap;y+=rowH+gap;rowH=0}
        it.x=x;it.y=y;x+=it.w+gap;rowH=Math.max(rowH,it.h);
    });
    $('#sheetLength').value=Math.max(1,Math.ceil((items.length?y+rowH+gap:12)*2)/2);
    renderSheet();items.forEach(position);select(null);renderDetails();
    return resized;
}
$('#autoBuild').onclick=async()=>{
    if(!items.length){for(const a of assets){for(let q=0;q<a.qty;q++)makeItem(a,0,0,a.width,a.height)}}
    const resized=autoArrangeItems();renderAssets();showBuilderPanel('canvas');
    status(`Auto Build complete. Artwork dimensions preserved.${resized?` ${resized} oversized piece(s): adjust the width/height inputs to fit.`:''}`);
};
function effectiveDpi(it){return it.rotation%180?Math.min(it.asset.pxH/it.w,it.asset.pxW/it.h):Math.min(it.asset.pxW/it.w,it.asset.pxH/it.h)}
// Union of clipped artwork rectangles prevents double-counting overlapping pieces.
function usedSheetArea(){
    const rects=items.filter(i=>[i.x,i.y,i.w,i.h].every(Number.isFinite)&&i.w>0&&i.h>0)
        .map(i=>({x1:Math.max(0,i.x),x2:Math.min(SHEET_W,i.x+i.w),y1:Math.max(0,i.y),y2:Math.min(sheetH(),i.y+i.h)}))
        .filter(r=>r.x2>r.x1&&r.y2>r.y1);
    const edges=[...new Set(rects.flatMap(r=>[r.x1,r.x2]))].sort((a,b)=>a-b);
    let area=0;
    for(let x=1;x<edges.length;x++){
        const intervals=rects.filter(r=>r.x1<edges[x]&&r.x2>edges[x-1]).sort((a,b)=>a.y1-b.y1);
        let length=0,end=0;
        for(const r of intervals){length+=Math.max(0,r.y2-Math.max(end,r.y1));end=Math.max(end,r.y2)}
        area+=(edges[x]-edges[x-1])*length;
    }
    return area;
}
function renderDetails(){
    const area=SHEET_W*sheetH(),used=usedSheetArea(),low=items.filter(i=>effectiveDpi(i)<250).length;
    $('#sheetMetric').textContent=`${SHEET_W} × ${sheetH()} in`;
    $('#sheetArea').textContent=`${round(area)} in²`;
    $('#pieceCount').textContent=items.length;
    $('#usedArea').textContent=`${round(used)} in²`;
    $('#remainingArea').textContent=`${round(Math.max(0,area-used))} in²`;
    $('#coverage').textContent=(area?used/area*100:0).toFixed(1)+'%';
    $('#exportSize').textContent=`${SHEET_W*300} × ${Math.round(sheetH()*300)} px`;
    $('#qualityCount').textContent=low?`${low} low resolution`:items.length?'Ready':'No artwork';
    $('#quality').textContent=low?'Increase resolution or reduce the highlighted artwork size.':items.length?'All placed artwork is print ready.':'Add artwork to start building your sheet.';
    if(artworkOutsideCanvas())$('#quality').textContent='Artwork is outside the canvas. Adjust size or position using the inputs before downloading.';
    $('#quality').classList.toggle('warn',!!low||artworkOutsideCanvas());updateWidthBanner();
}
$('#centerArtwork').onclick=()=>{if(!selected)return status('Select artwork to center.');selected.x=(SHEET_W-selected.w)/2;position(selected);renderProps();renderDetails()};
$('#trimSheet').onclick=()=>{
    const gap=Math.min(2,Math.max(0,+$('#gap').value||0));
    $('#sheetLength').value=items.length?Math.max(1,Math.ceil((Math.max(...items.map(i=>i.y+i.h))+gap)*2)/2):12;
    renderSheet();status('Sheet length fitted to the placed artwork.');
};
function outsideCanvas(it){const eps=0.000001;return ![it.x,it.y,it.w,it.h].every(Number.isFinite)||it.w<=0||it.h<=0||it.x< -eps||it.y< -eps||it.x+it.w>SHEET_W+eps||it.y+it.h>sheetH()+eps}
function artworkOutsideCanvas(){return items.some(outsideCanvas)}
function updateWidthBanner(focus=false){
    const oversized=items.filter(outsideCanvas),banner=$('#widthBanner');
    banner.hidden=!oversized.length;
    banner.textContent=oversized.length?'Cannot download — artwork is outside the canvas: '+[...new Set(oversized.map(it=>it.asset.name))].join('; ')+'. Adjust the width/height or position inputs to fit the artwork within the sheet.':'';
    if(focus&&oversized.length){banner.focus();banner.scrollIntoView({block:'nearest'});status('Download blocked: artwork is outside the canvas.',true);window.alert(banner.textContent)}
}

async function savePrintSheet(sendToPrinter=false){
    if(exporting)return;
    if(artworkOutsideCanvas())return updateWidthBanner(true);
    if(!items.length)return status('Add artwork before exporting the print sheet.',true);
    if(sendToPrinter&&!PrintOrderSender.ready())return;
    exporting=true;$('#saveSheet').disabled=true;if($('#sendPrint'))$('#sendPrint').disabled=true;
    let canvas;
    try{
        const dpi=300,width=Math.round(SHEET_W*dpi),height=Math.round(sheetH()*dpi);
        if(!Number.isFinite(height)||height<1||width>32767||height>32767||width*height>256000000)throw new Error('This sheet is too large to export in the browser at 300 DPI. Shorten the sheet or divide the artwork into multiple sheets.');
        if(sendToPrinter)PrintOrderSender.validate(width,height);
        await PrintProgress.start(sendToPrinter?'Preparing gang sheet for CSW…':'Preparing print sheet…');
        canvas=document.createElement('canvas');canvas.width=width;canvas.height=height;
        const ctx=canvas.getContext('2d');if(!ctx)throw new Error('Your browser could not create the print sheet.');
        status(`Rendering ${width.toLocaleString()} × ${height.toLocaleString()} px at 300 DPI…`);
        for(const [index,it] of items.entries()){
            PrintProgress.update(`Rendering artwork ${index+1} of ${items.length}…`);await PrintProgress.paint();
            const image=await loadExportImage(it.asset.url),x=(it.x+it.w/2)*dpi,y=(it.y+it.h/2)*dpi;
            ctx.save();ctx.translate(x,y);ctx.rotate(it.rotation*Math.PI/180);
            const drawW=(it.rotation%180?it.h:it.w)*dpi,drawH=(it.rotation%180?it.w:it.h)*dpi;
            ctx.drawImage(image,-drawW/2,-drawH/2,drawW,drawH);ctx.restore();
        }
        PrintProgress.update('Encoding print PNG…');await PrintProgress.paint();
        const blob=await new Promise((resolve,reject)=>canvas.toBlob(b=>b?resolve(b):reject(new Error('Your browser could not encode this sheet at 300 DPI. Try a shorter sheet or close other tabs to free memory.')),'image/png'));
        const printBlob=await addPngDpi(blob,dpi),filename=`Printrivo-${SHEET_W}x${sheetH()}in-300dpi.png`;
        if(sendToPrinter){const receipt=await PrintOrderSender.send({blob:printBlob,filename,tool:'gang-sheet',dpi,width,height});status(receipt?'Gang sheet sent to CSW.':'Print order canceled.');}
        else{
            // Retain a real, user-clickable link if the browser blocks the async download.
            if(downloadUrl)URL.revokeObjectURL(downloadUrl);
            downloadUrl=URL.createObjectURL(printBlob);
            const link=$('#downloadFile');link.href=downloadUrl;link.download=filename;link.hidden=false;
            $('#downloadTitle').textContent='Your print sheet is ready';
            $('#downloadMessage').textContent=`${width.toLocaleString()} × ${height.toLocaleString()} px, 300 DPI. If the download does not start automatically, click Save PNG.`;
            PrintProgress.finish();$('#downloadResult').showModal();link.click();
            status('Print sheet ready. Use Save PNG if the download did not start.');
        }
    }catch(error){
        const message=error.message||'The print sheet could not be exported.';
        status(message,true);
        if(sendToPrinter)PrintOrderSender.feedback(message,true);
        else{PrintProgress.finish();$('#downloadTitle').textContent='Print sheet could not be saved';$('#downloadMessage').textContent=message;$('#downloadFile').hidden=true;$('#downloadResult').showModal();}
    }
    finally{if(canvas)canvas.width=canvas.height=1;exporting=false;$('#saveSheet').disabled=false;if($('#sendPrint'))$('#sendPrint').disabled=false;PrintProgress.finish();}
}

function loadExportImage(url){return new Promise((resolve,reject)=>{const image=new Image;image.onload=()=>resolve(image);image.onerror=()=>reject(new Error('One of the artwork files could not be rendered.'));image.src=url})}
async function addPngDpi(blob,dpi){
    const ppm=Math.round(dpi/0.0254),chunk=new Uint8Array(21),view=new DataView(chunk.buffer);
    view.setUint32(0,9);chunk.set(new TextEncoder().encode('pHYs'),4);
    view.setUint32(8,ppm);view.setUint32(12,ppm);chunk[16]=1;
    view.setUint32(17,crc32(chunk.subarray(4,17)));
    // Keep compressed image data as blob slices instead of copying the entire PNG.
    // Replace an existing resolution chunk so the PNG has exactly one pHYs chunk.
    const parts=[blob.slice(0,33),chunk];let offset=33;
    while(offset+12<=blob.size){
        const header=new DataView(await blob.slice(offset,offset+8).arrayBuffer());
        const length=header.getUint32(0)+12;
        if(offset+length>blob.size)throw new Error('The browser produced an incomplete PNG. Please retry the export.');
        if(header.getUint32(4)!==0x70485973)parts.push(blob.slice(offset,offset+length));
        offset+=length;
    }
    return new Blob(parts,{type:'image/png'});
}
function crc32(bytes){let crc=0xffffffff;for(const byte of bytes){crc^=byte;for(let i=0;i<8;i++)crc=(crc>>>1)^((crc&1)?0xedb88320:0)}return(crc^0xffffffff)>>>0}
$('#sheetWidth').onchange=()=>{SHEET_W=+$('#sheetWidth').value===22?22:16;autoArrangeItems();status(`Sheet set to ${SHEET_W} inches wide. Artwork arranged with dimensions preserved.`)};
$('#gap').onchange=()=>items.length&&autoArrangeItems();$('#sheetLength').onchange=()=>{$('#sheetLength').value=Math.max(1,+$('#sheetLength').value||1);renderSheet()};$('#saveSheet').onclick=()=>savePrintSheet();if($('#sendPrint'))$('#sendPrint').onclick=()=>savePrintSheet(true);sheet.onclick=e=>{if(e.target===sheet)select(null)};function round(n){return Math.round(n*100)/100}function esc(s){return s.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}renderSheet();
</script>
<?= view('security/recaptcha') ?>
<?= view('partials/report_bug_dialog', ['toolRoute' => 'tools/gang-sheet']) ?>
</body></html>
