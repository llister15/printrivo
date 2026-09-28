'use strict';
/* Adapt established controls without replacing the background/color algorithms. */
window.createLegacyStudioPreviews=function(host){
 const $=id=>document.getElementById(id),tools=[];
 const configs=[
 {processor:'background',panel:'background-removal',apply:'remove-bg',fields:{source:'bg-source',color:'bg-color',tolerance:'tolerance',softness:'bg-feather',area:'bg-area'}},
 {processor:'adjustments',panel:'color-adjustments',apply:'colors',fields:{brightness:'brightness',contrast:'contrast',saturation:'saturation'}}
 ];
 for(const config of configs){
   const panel=$(config.panel),label=document.createElement('label');label.textContent='Preview';const view=document.createElement('select');view.add(new Option('Original','original'));view.add(new Option('Result','result'));view.value='result';view.id='legacy-'+config.processor+'-view';label.append(view);
   const status=document.createElement('p');status.className='hint';status.setAttribute('role','status');const reset=document.createElement('button'),cancel=document.createElement('button');reset.textContent='Reset settings';cancel.textContent='Cancel Preview';reset.id='legacy-'+config.processor+'-reset';cancel.id='legacy-'+config.processor+'-cancel';const buttons=document.createElement('div');buttons.className='grid';buttons.append(reset,cancel);panel.insertBefore(label,$(config.apply));panel.insertBefore(status,$(config.apply));panel.insertBefore(buttons,$(config.apply));
   const read=()=>Object.fromEntries(Object.entries(config.fields).map(([key,id])=>[key,$(id).type==='range'?+$(id).value:$(id).value]));
   const session=new StudioPreview.Preview({...host,status:(text,error)=>{status.textContent=text;status.classList.toggle('error',!!error);if(session.active)host.notify(text,error);}},config.processor);
   const schedule=()=>session.schedule(read(),view.value);
   for(const id of Object.values(config.fields))$(id).addEventListener('input',schedule);
   reset.onclick=()=>{for(const id of Object.values(config.fields)){const field=$(id);if(field.tagName==='SELECT')field.selectedIndex=0;else field.value=field.defaultValue;}schedule();};
   cancel.onclick=()=>{session.cancel(true);status.textContent='Preview canceled.';};view.onchange=schedule;
   panel.addEventListener('toggle',()=>{if(host.busy())return;if(panel.open)schedule();else session.cancel(true);});
   for(let parent=panel.parentElement;parent;parent=parent.parentElement)if(parent.tagName==='DETAILS')parent.addEventListener('toggle',()=>{if(!parent.open&&!host.busy())session.cancel(true);});
   $(config.apply).onclick=()=>{const settings=read();host.commit(config.processor==='background'?'Background removed':'Color adjustments applied',async()=>session.render(host.source(),settings,{documentWidth:host.source().width,documentHeight:host.source().height}));};tools.push(session);
 }
 return tools;
};
