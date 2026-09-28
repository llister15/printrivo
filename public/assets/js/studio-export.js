'use strict';
window.createStudioExport=function(host){
 const $=id=>document.getElementById(id),dialog=$('export-dialog');let report=null,opening=false;
 function options(){return {mirror:$('export-mirror').checked,trim:$('export-trim').checked,pad:$('export-padding').checked?Number($('export-pad').value):0};}
 function geometry(){if(report?.analysis?.visible===0)throw new Error('No visible artwork to export. Undo the last edit or open an image with visible artwork.');const source=host.source(),s=options(),bounds=s.trim&&report?.analysis?.bounds?report.analysis.bounds:{x:0,y:0,width:source.width,height:source.height};const width=bounds.width+s.pad*2,height=bounds.height+s.pad*2;if(!Number.isInteger(s.pad)||s.pad<0||!Number.isFinite(width*height)||width<1||height<1||width>30000||height>30000||width*height>256000000)throw new Error('Output must stay within 30,000 px per side and 256 MP. Enter whole-pixel padding.');return {...bounds,outputWidth:width,outputHeight:height,...s};}
 function refresh(){
   if(!host.source())return;host.mirror($('export-mirror').checked);const dpi=host.dpi(),rows=report?.rows||[],warnings=rows.filter(r=>r.status!=='PASS');
   $('export-title').textContent=warnings.length?'EXPORT WITH WARNINGS':'READY TO EXPORT';$('export-issues').replaceChildren();for(const row of warnings){const p=document.createElement('p');p.textContent=`${row.status==='ERROR'?'✕':'⚠'} ${row.detail}`;$('export-issues').append(p);}
   if(!report?.analysis){const p=document.createElement('p');p.textContent='⚠ Analysis is unavailable. Inspect artwork before exporting.';$('export-issues').append(p);}
   try{const g=geometry();$('export-summary').textContent=`PNG · ${g.outputWidth.toLocaleString()} × ${g.outputHeight.toLocaleString()} px\n${(g.outputWidth/dpi).toFixed(2)} × ${(g.outputHeight/dpi).toFixed(2)} in at ${dpi} DPI\nRGB / sRGB\n${report?.analysis?.clear||g.pad?'Transparency present':'Opaque background'}${g.mirror?'\n⚠ MIRROR ENABLED — exported artwork will be horizontally mirrored.':''}`;$('export-confirm').disabled=false;$('export-error').textContent='';}
   catch(error){$('export-confirm').disabled=true;$('export-error').textContent=error.message;}
   $('export-confirm').textContent=warnings.length?'Export Anyway':'Export PNG';
 }
 for(const id of ['export-mirror','export-trim','export-padding','export-pad'])$(id).oninput=refresh;
 $('export-cancel').onclick=$('export-fix').onclick=()=>dialog.close();
 $('export-confirm').onclick=async()=>{let g;try{g=geometry();}catch(error){$('export-error').textContent=error.message;return;}$('export-confirm').disabled=true;await host.export(g);if(!host.busy())dialog.close();};
 return {async open(){if(opening||host.busy()||!host.source())return;opening=true;let original=host.source();host.cancelPreview();host.notify('Analyzing artwork for export…');try{await PrintProgress.start('Checking artwork before export…');report=await host.preflight();while(host.source()&&host.source()!==original){original=host.source();report=await host.preflight();}if(!host.source())return;$('export-mirror').checked=host.mirrored();refresh();dialog.showModal();}finally{opening=false;PrintProgress.finish();}},close(){dialog.close();},refresh};
};
