const {readFileSync}=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const {webcrypto}=require('node:crypto');
const root=path.resolve(__dirname,'../..');
const span={},button={},box={dataset:{connected:'1',endpoint:'/api/print-orders',authorizeEndpoint:'/api/print-orders/authorize',csrfHeader:'X-CSRF-TOKEN',csrfToken:'test-csrf'},querySelector:s=>s==='span'?span:button};
const sent=[],authorizations=[],storage=new Map();let response={ok:true,order_number:'FP-1',duplicate:false},networkFailure=true;
class XHR {
 constructor(){this.upload={};this.headers={};}
 open(method,url){this.method=method;this.url=url;}
 setRequestHeader(name,value){this.headers[name]=value;}
 send(data){sent.push({data,headers:this.headers});queueMicrotask(()=>{if(networkFailure)this.onerror();else{this.status=201;this.response=response;this.onload();}});}
}
const context=vm.createContext({window:{PrintOrderConfirmation:{open:async()=>({fulfillment:'pickup',shipping_address:'',confirmed_price_cents:'1500'})}},document:{getElementById:()=>box},PrintProgress:{update(){}},AbortSignal,fetch:async(url,options)=>{authorizations.push({url,options});return {ok:true,json:async()=>({ok:true,upload_ticket:'ticket-'+authorizations.length})};},crypto:webcrypto,FormData,XMLHttpRequest:XHR,sessionStorage:{getItem:k=>storage.get(k),setItem:(k,v)=>storage.set(k,v)},console});
vm.runInContext(readFileSync(root+'/public/assets/js/print-order.js','utf8'),context);
(async()=>{
 const sender=context.window.PrintOrderSender,art={blob:new Blob(['png test'],{type:'image/png'}),filename:'sheet.png',tool:'gang-sheet',dpi:300,width:4800,height:3600};
 await assert.rejects(sender.send(art),/not confirmed/);
 networkFailure=false;await sender.send(art);
 assert.equal(sent[0].data.get('request_id'),sent[1].data.get('request_id'),'network retries must use the same ID');
 assert.equal(sent[1].headers['X-CSRF-TOKEN'],'test-csrf');
 assert.equal(authorizations[0].url,'/api/print-orders/authorize');
 assert.equal(sent[0].headers['X-Printrivo-Upload-Ticket'],'ticket-1');
 assert.equal(sent[1].headers['X-Printrivo-Upload-Ticket'],'ticket-2');
 assert.equal(authorizations[1].options.body.get('request_id'),sent[1].data.get('request_id'));
 assert.equal(sent[1].data.has('g-recaptcha-response'),false);
 assert.equal(sent[1].data.get('tool'),'gang-sheet');
 assert.match(span.textContent,/Sent to CSW — order FP-1/);
 response={ok:true,order_number:'FP-1',duplicate:true};await sender.send(art);assert.match(span.textContent,/Already sent/);
 context.window.PrintOrderConfirmation.open=async()=>null; const beforeCancel=sent.length;await sender.send(art);assert.equal(sent.length,beforeCancel);
 context.window.PrintOrderConfirmation.open=async()=>({fulfillment:'shipping',shipping_address:'123 Main St, Town, USA',confirmed_price_cents:'1500'});
 const count=sent.length;await assert.rejects(sender.send({...art,width:16001,height:16000}),/256 MP/);assert.equal(sent.length,count);
 await assert.rejects(sender.send({...art,blob:{size:500*1024*1024+1}}),/500 MB/);assert.equal(sent.length,count);
 assert.doesNotThrow(()=>sender.validate(20550,11559));
 await sender.send({...art,blob:new Blob([new Uint8Array(9*1024*1024)])});assert.equal(sent.length,count+1);
 box.dataset.connected='0';assert.equal(sender.ready(),false);assert.match(span.textContent,/not connected/);
 console.log('PASS: stable print retry IDs, CSRF, success/duplicate feedback, CSW size limits and disconnected configuration');
 // Exercise the real resize processor used in both worker and fallback modes.
 const draws=[];
 class Canvas{constructor(w,h){this.width=w;this.height=h;}getContext(){return {drawImage(...args){draws.push(args);},getImageData(){return {data:new Uint8ClampedArray([0,0,0,255])};}};}}
 const worker=vm.createContext({OffscreenCanvas:Canvas,setTimeout});
 vm.runInContext(readFileSync(root+'/public/assets/js/studio-processors.js','utf8'),worker);
 const source={width:100,height:50};
 const result=await worker.StudioProcessors.render(source,'resize',{width:20550,height:11559});
 assert.equal(result.canvas.width,20550);assert.equal(result.canvas.height,11559);assert.deepEqual(draws[0],[source,0,0,20550,11559]);
 await assert.rejects(worker.StudioProcessors.render(source,'resize',{width:30001,height:1}),/30,000/);
 await assert.rejects(worker.StudioProcessors.render(source,'resize',{width:16001,height:16000}),/256 MP/);
 console.log('PASS: resize processor accepts banner dimensions and rejects edge/area overflow');
})().catch(error=>{console.error(error);process.exitCode=1;});
