const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const read = name => fs.readFileSync(path.join(root, 'public/assets/js', name), 'utf8');
// Exercise the resize guard without allocating a gigabyte-sized bitmap.
const studio = read('image-studio.js');
const guard = vm.createContext({});
vm.runInContext(studio.match(/const MAX_PIXELS[^;]+;/)[0] + studio.match(/function dimensionsOK\(w,h\) \{[^\n]+/)[0] + ';this.check=dimensionsOK;', guard);
assert.doesNotThrow(() => guard.check(20550, 11559));
assert.doesNotThrow(() => guard.check(16000, 16000));
assert.throws(() => guard.check(30001, 1), /30,000/);
assert.throws(() => guard.check(16001, 16000), /256 MP/);
assert.throws(() => guard.check(NaN, 10));
// Verify the export dialog permits the banner and blocks oversized padding.
const elements = new Map();
const element = id => {
  if (!elements.has(id)) elements.set(id, {checked:false,value:'0',replaceChildren(){},append(){},showModal(){},close(){}});
  return elements.get(id);
};
const context = vm.createContext({PrintProgress:{start:async()=>{},finish(){}},window:{},document:{getElementById:element,createElement:()=>({})}});
vm.runInContext(read('studio-export.js'), context);
const source = {width:20550,height:11559};
let report = {rows:[]};
const ui = context.window.createStudioExport({source:()=>source,dpi:()=>300,mirror(){},busy:()=>false,cancelPreview(){},notify(){},mirrored:()=>false,preflight:async()=>report});
(async()=>{
 await ui.open();
 assert.equal(element('export-confirm').disabled,false);
 assert.match(element('export-summary').textContent,/20,550 × 11,559/);
 element('export-padding').checked=true; element('export-pad').value='2000'; ui.refresh();
 assert.equal(element('export-confirm').disabled,true);
 assert.match(element('export-error').textContent,/256 MP/);
 element('export-pad').value='50'; ui.refresh();
 assert.equal(element('export-confirm').disabled,false);
 report={rows:[],analysis:{visible:0}}; await ui.open();
 assert.equal(element('export-confirm').disabled,true);
 assert.match(element('export-error').textContent,/No visible artwork/);
 console.log('PASS: empty export blocked, banner resize/export, boundary limits, invalid dimensions, and padded export validation');
})().catch(error=>{console.error(error);process.exitCode=1;});
