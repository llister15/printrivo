'use strict';
(() => {
 const symbols={PASS:'✓',WARNING:'⚠',ERROR:'✕'};
 function checks(a,m){
   const {width:w,height:h}=a,iw=m.printWidth,ih=m.printHeight,dpi=Math.min(w/iw,h/ih),valid=Number.isFinite(dpi)&&iw>0&&ih>0;
   const rows=[],add=(name,status,detail,tool)=>rows.push({name,status,detail,tool});
   add('Resolution',!valid?'ERROR':dpi<100?'ERROR':dpi<200?'WARNING':'PASS',!valid?'Enter positive print dimensions in Resize image & print.':`${w.toLocaleString()} × ${h.toLocaleString()} px at ${iw.toFixed(2)} × ${ih.toFixed(2)} in: ${dpi.toFixed(1)} effective DPI. ${dpi<200?'Small details may look soft. Use Upscale for 300 DPI or choose a smaller print size.':'Suitable pixel density at the selected size.'}`,'resize-panel');
   const mismatch=valid&&(Math.abs(w/m.dpi-iw)>.01||Math.abs(h/m.dpi-ih)>.01);
   add('Print size',!valid?'ERROR':mismatch||Math.min(iw,ih)<.25?'WARNING':'PASS',valid?`${iw.toFixed(2)} × ${ih.toFixed(2)} in requested. ${m.dpi} DPI export metadata. ${mismatch?`Without resizing, export measures ${(w/m.dpi).toFixed(2)} × ${(h/m.dpi).toFixed(2)} in. Apply Resize to match the requested print size.`:Math.min(iw,ih)<.25?'Very small artwork: confirm this size before printing.':'Dimensions match the PNG export size.'}`:'Print dimensions must be greater than zero.','resize-panel');
   add('Background',!a.clear||a.solidEdge?'WARNING':'PASS',!a.clear?'No transparent pixels detected. An opaque background may print as a rectangle; keep it if intentional, or open Background tools.':a.solidEdge?'A mostly uniform opaque color touches the canvas perimeter. Inspect for an unwanted background and use Remove Background if needed.':'Transparency detected; no uniform opaque perimeter detected.','background-removal');
   add('Edge quality',a.low||a.partial>Math.max(20,a.visible*.02)?'WARNING':'PASS',a.low?`${a.low.toLocaleString()} pixels have less than 10% opacity; they may create faint print residue. Inspect Transparency cleanup before removing them.`:a.partial>Math.max(20,a.visible*.02)?`${a.partial.toLocaleString()} partially transparent pixels detected. These can be intentional shading; inspect Clean edges or Alpha Mask before hardening them.`:'No widespread low-opacity residue detected. Inspect fine edges at 100% zoom.','tool-edges');
   add('Color','PASS','The decoded editing surface and PNG export use RGB / sRGB. Original file profiles or source color mode are not inferred from canvas pixels.','colors-panel');
   const padding=a.bounds?1-a.bounds.width*a.bounds.height/(w*h):1;
   add('Canvas',!a.visible?'ERROR':m.outsideText?'WARNING':padding>.6||a.touch||w*h>40000000?'WARNING':'PASS',!a.visible?'No visible artwork detected. Open an image or add visible content.':m.outsideText?'A text layer extends outside the canvas and will be clipped on export. Reposition it or add padding.':padding>.6?'More than 60% of the canvas lies outside the artwork bounds. Trim transparent padding to avoid oversized placement.':a.touch?'Visible pixels touch the canvas edge. Add padding if you need space around the artwork.':w*h>40000000?'Canvas exceeds 40 MP. Large exports require more memory; trim unused padding if present.':'Artwork fits inside the canvas with transparent padding.','tool-padding');
   return rows;
 }
 window.StudioPreflight={checks,symbols,create(host){
   const job=new StudioPreview.Job();let analysis=null,key=null,generation=0,pending=null,lastRows=[];
   const $=id=>document.getElementById(id);
   function render(){
     if(!host.source()){$('preflight-checks').replaceChildren();$('preflight-summary').textContent='Open an image to check whether your artwork is ready for print.';$('preflight-status').textContent='NO IMAGE';$('preflight-status').dataset.level='empty';$('quick-fixes').replaceChildren();lastRows=[];return;}
     if(!analysis)return;
     lastRows=checks(analysis,host.metadata());$('preflight-checks').replaceChildren();
     for(const row of lastRows){const item=document.createElement('details'),summary=document.createElement('summary'),text=document.createElement('p');item.className='check-item';item.dataset.level=row.status;summary.textContent=`${symbols[row.status]} ${row.name}`;text.textContent=row.detail;item.append(summary,text);$('preflight-checks').append(item);}
     const errors=lastRows.filter(r=>r.status==='ERROR').length,warnings=lastRows.filter(r=>r.status==='WARNING').length,issues=errors+warnings;
     $('preflight-summary').textContent=issues?`${issues} ${issues===1?'issue needs':'issues need'} attention`:'No issues detected.';
     $('preflight-status').textContent=errors?'✕ NOT PRINT READY':warnings?'⚠ NEEDS ATTENTION':'✓ READY TO PRINT';$('preflight-status').dataset.level=errors?'ERROR':warnings?'WARNING':'PASS';
     $('quick-fixes').replaceChildren();for(const row of lastRows.filter(r=>r.status!=='PASS'&&!(r.name==='Canvas'&&r.status==='ERROR'))){const label={Resolution:'Upscale to 300 DPI',Background:'Remove Background','Edge quality':'Clean Edges',Canvas:'Canvas & padding','Print size':'Review Print Size'}[row.name];if(!label)continue;const button=document.createElement('button');button.textContent=label;button.onclick=()=>host.openTool(row.tool);$('quick-fixes').append(button);}
     $('quick-fixes-section').hidden=!$('quick-fixes').children.length;
   }
   function update(){const source=host.source();if(!source){generation++;job.cancel();analysis=null;key=null;render();return Promise.resolve();}
     const nextKey=host.key();if(nextKey===key){render();return pending||Promise.resolve();}key=nextKey;analysis=null;const token=++generation;$('preflight-summary').textContent='Analyzing artwork…';$('preflight-status').textContent='ANALYZING';$('preflight-checks').replaceChildren();$('quick-fixes').replaceChildren();
     pending=(async()=>{try{const result=await job.run(host.composite(),'analyze');if(token!==generation)return;analysis=result.analysis;render();}catch(error){if(token===generation&&error.name!=='AbortError'){$('preflight-summary').textContent='Analysis could not finish. Inspect the artwork before printing.';$('preflight-status').textContent='⚠ NEEDS ATTENTION';key=null;}}})();return pending;
   }
   return {update,refresh:render,ready:async()=>{await update();let token;do{token=generation;if(pending)await pending;}while(token!==generation);render();return {analysis,rows:lastRows};},get:()=>({analysis,rows:lastRows})};
 }};
})();
