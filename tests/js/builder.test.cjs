const {readFileSync}=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const source=readFileSync(require('node:path').join(__dirname,'../../app/Views/tools/gang_sheet.php'),'utf8').match(/<script>\s*([\s\S]*?)<\/script>/)[1];
const nodes=new Map();
function node(key){if(!nodes.has(key))nodes.set(key,{value:key==='#sheetLength'?'12':key==='#gap'?'.25':'',style:{},setAttribute(){},classList:{toggle(){},add(){},remove(){}},addEventListener(){},querySelector(){return node('image')},querySelectorAll(){return []},focus(){},scrollIntoView(){}});return nodes.get(key)}
let alerts=0;
const context=vm.createContext({document:{querySelector:node,querySelectorAll:()=>[]},Intl,console,window:{alert(){alerts++}}});
vm.runInContext(source,context);
function run(code){return vm.runInContext(code,context)}
assert.equal(node('#pieceCount').textContent,0);
assert.equal(node('#usedArea').textContent,'0 in²');
assert.equal(node('#remainingArea').textContent,'192 in²');
assert.equal(node('#exportSize').textContent,'4800 × 3600 px');
run("$('#sheetLength').value=100;renderDetails()");
for(const [x,y,w,h] of [[-1,0,1,1],[0,-1,1,1],[15,0,2,1],[0,99,1,2],[0,0,17,1]])assert.equal(run(`outsideCanvas({x:${x},y:${y},w:${w},h:${h}})`),true);
assert.equal(run('outsideCanvas({x:0,y:0,w:16,h:100})'),false);
run("items.push({x:0,y:0,w:20,h:40,rotation:0,asset:{name:'oversize',pxW:6000,pxH:12000},el:sheet},{x:0,y:0,w:8,h:12,rotation:90,asset:{name:'rotated',pxW:3600,pxH:2400},el:sheet})");
run('savePrintSheet()');assert.equal(alerts,1);
assert.equal(run('autoArrangeItems()'),1);
assert.equal(run('items.some(outsideCanvas)'),true);
assert.equal(run('items[0].h/items[0].w'),2);
assert.equal(run('items.length'),2);
assert.equal(run('items[1].rotation'),90);
assert.equal(run('items[1].y>=items[0].y+items[0].h+.25'),true);

// Overlap must count only once, and rectangles must clip to the sheet edges.
run("items.length=0;$('#sheetLength').value=10;items.push({x:0,y:0,w:4,h:4},{x:2,y:2,w:4,h:4})");
assert.equal(run('usedSheetArea()'),28);
run('items.push({x:15,y:9,w:4,h:4})');
assert.equal(run('usedSheetArea()'),29);
run('items.push({x:30,y:0,w:2,h:2})');
assert.equal(run('usedSheetArea()'),29);
run("items.length=0;items.push({x:1,y:2,w:4,h:3,rotation:0,asset:{pxW:1200,pxH:900},el:sheet});selected=items[0];$('#centerArtwork').onclick()");
assert.equal(run('selected.x'),6);
assert.equal(run('selected.y'),2);
run("$('#trimSheet').onclick()");
assert.equal(run('sheetH()'),5.5);
assert.equal(run('items[0].y'),2);
assert.equal(node('#usedArea').textContent,'12 in²');
assert.equal(node('#remainingArea').textContent,'76 in²');
assert.equal(node('#coverage').textContent,'13.6%');
assert.equal(node('#exportSize').textContent,'4800 × 1650 px');
run("items.length=0;$('#trimSheet').onclick()");
assert.equal(run('sheetH()'),12);
console.log('Passed: usage, overlap, clipping, centering, trimming, export bounds, preserved dimensions, spacing, rotation and piece preservation.');

run("$('#sheetWidth').value='22';$('#sheetWidth').onchange()");
assert.equal(run('SHEET_W'),22);
assert.equal(node('#sheetMetric').textContent,'22 × 12 in');
assert.equal(node('#exportSize').textContent,'6600 × 3600 px');
assert.equal(run('outsideCanvas({x:0,y:0,w:20,h:4})'),false);
run("items.push({x:0,y:0,w:20,h:4,rotation:0,asset:{pxW:6000,pxH:1200},el:sheet});$('#sheetWidth').value='16';$('#sheetWidth').onchange()");
assert.equal(run('SHEET_W'),16);
assert.equal(run('items.some(outsideCanvas)'),true);
assert.equal(run('items[0].w'),20);
assert.equal(run('items[0].h/items[0].w'),.2);
console.log('Passed: 16/22-inch dimensions, export size and narrowing-sheet arrangement.');

run("window.innerWidth=390;$('#sheetWidth').value='22';$('#sheetWidth').onchange();renderSheet()");
assert.equal(run('SHEET_W'),22);
assert.ok(run('PX*SHEET_W')<=350);
assert.ok(node('#exportSize').textContent.startsWith('6600 × '));
run('window.innerWidth=1200;renderSheet()');
assert.equal(run('PX'),24);
console.log('Passed: mobile canvas fits while export keeps full 300 DPI width.');

// Arrangement is automatic, but it must preserve the original artwork and dimensions.
run("items.length=0;items.push({x:18,y:15,w:4,h:3,rotation:0,asset:{url:'blob:original',pxW:1200,pxH:900},el:sheet});$('#sheetWidth').value='16';$('#sheetWidth').onchange();$('#gap').value=1;$('#gap').onchange()");
assert.equal(run('items[0].x'),1);
assert.equal(run('items[0].y'),1);
assert.equal(run('items[0].w'),4);
assert.equal(run('items[0].h'),3);
assert.equal(run('items[0].asset.url'),'blob:original');
assert.equal(run('artworkOutsideCanvas()'),false);
console.log('Passed: automatic arrangement preserves image source and dimensions.');

// Opaque or transparent artwork must keep the source URL and full dimensions.
(async()=>{
    run(`assets.length=0;items.length=0;
        readArtworkSource=async()=>({url:'blob:original-upload',dim:{w:9000,h:3000}});
        imagePrintInfo=async()=>({dpi:300,dpiMissing:false});
        renderAssets=()=>{};
        globalThis.dtfProgress={start:()=>true,show:()=>{},finish:()=>{}};
        globalThis.requestAnimationFrame=fn=>fn();globalThis.setTimeout=fn=>fn();
        globalThis.crypto={randomUUID:()=> 'test-artwork'};
        drawItem=it=>{it.el=sheet};
    `);
    await run("addFiles([{name:'original.jpg',type:'image/jpeg',size:100}])");
    assert.equal(run('assets[0].url'),'blob:original-upload');
    assert.equal(run('assets[0].pxW'),9000);
    assert.equal(run('assets[0].width'),30);
    run('makeItem(assets[0]);autoArrangeItems()');
    assert.equal(run('items[0].w'),30);
    assert.equal(run('items[0].h'),10);
    run("select(items[0]);$('#pw').value=12;$('#pw').oninput()");
    assert.equal(run('items[0].w'),12);
    assert.equal(run('items[0].h'),4);
    assert.equal(run('items[0].asset.url'),'blob:original-upload');
    console.log('Passed: original upload preserved; only size inputs resize artwork.');
})().catch(error=>{console.error(error);process.exitCode=1});
