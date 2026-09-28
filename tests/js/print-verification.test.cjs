const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path'),assert=require('node:assert/strict');
const {webcrypto}=require('node:crypto');
const root=path.resolve(__dirname,'../..');
async function check(file){
 const box={dataset:{connected:'1',captchaRequired:'1',endpoint:'/api/print-orders',authorizeEndpoint:'/api/print-orders/authorize',csrfHeader:'X-CSRF-TOKEN',csrfToken:'csrf'},querySelector:()=>({})};
 const calls=[],uploads=[];let grantOK=false,tokens=0;
 class XHR{
  constructor(){this.upload={};this.headers={};}
  open(){}setRequestHeader(k,v){this.headers[k]=v;}
  send(body){uploads.push({body,headers:this.headers});this.status=201;this.response={ok:true,order_number:'TEST'};this.onload();}
 }
 const window={PrintOrderConfirmation:{open:async()=>({fulfillment:'pickup',shipping_address:'',confirmed_price_cents:'1500'})},printrivoRecaptcha:{token:async action=>{assert.equal(action,'form_submit');return 'fresh-'+(++tokens);}}};
 const context=vm.createContext({window,document:{getElementById:()=>box},PrintProgress:{update(){}},crypto:webcrypto,FormData,AbortSignal,XMLHttpRequest:XHR,
  fetch:async(url,opts)=>{calls.push({url,opts});assert.equal(opts.body.has('artwork'),false);return {ok:grantOK,json:async()=>grantOK?{ok:true,upload_ticket:'grant-'+tokens}:{ok:false,error:'verification rejected'}};}});
 vm.runInContext(fs.readFileSync(file,'utf8'),context);
 const artwork={blob:new Blob(['pixels'],{type:'image/png'}),filename:'art.png',tool:'image-studio',dpi:300,width:10,height:10};
 await assert.rejects(window.PrintOrderSender.send(artwork),/verification rejected/);
 assert.equal(uploads.length,0,'Never upload artwork when authorization fails');
 grantOK=true;await window.PrintOrderSender.send(artwork);
 assert.equal(uploads.length,1);assert.equal(tokens,2);assert.equal(calls[1].opts.body.get('g-recaptcha-response'),'fresh-2');
 assert.equal(uploads[0].headers['X-Printrivo-Upload-Ticket'],'grant-2');
 assert.equal(uploads[0].body.has('g-recaptcha-response'),false);
 assert.equal(calls[1].opts.headers['X-CSRF-TOKEN'],'csrf');
 assert.equal(calls[1].opts.body.get('request_id'),uploads[0].body.get('request_id'));
 window.printrivoRecaptcha=null;await assert.rejects(window.PrintOrderSender.send(artwork),/could not load/);assert.equal(uploads.length,1);
}
(async()=>{for(const file of ['public/assets/js/print-order.js','build/print-confirmation-fix/public/assets/js/print-order.js'])await check(path.join(root,file));console.log('PASS: both senders verify before upload, block failed/missing CAPTCHA, preserve CSRF and request identity, and request fresh grants on retries');})().catch(error=>{console.error(error);process.exitCode=1;});
