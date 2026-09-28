'use strict';
(() => {
    const url=document.currentScript?.src;
    const workerURL=url?new URL('studio-worker.js?v=20260924-local-ai',url).href:null;
    const sessions=new Set();
    class Job {
        constructor(){this.generation=0;this.worker=null;this.reject=null;}
        cancel(){this.generation++;if(this.worker){this.worker.onmessage=null;this.worker.onerror=null;this.worker.terminate();this.worker=null;}const reject=this.reject;this.reject=null;reject?.(new DOMException('Canceled','AbortError'));}
        async run(source,processor,settings={},options={}){
            this.cancel();const token=this.generation;
            if(workerURL&&typeof Worker!=='undefined'&&typeof OffscreenCanvas!=='undefined'){
                const bitmap=await createImageBitmap(source);
                if(token!==this.generation){bitmap.close();throw new DOMException('Canceled','AbortError');}
                return new Promise((resolve,reject)=>{
                    let worker;
                    const done=()=>{if(worker){worker.onmessage=null;worker.onerror=null;worker.terminate();}if(this.worker===worker){this.worker=null;this.reject=null;}};
                    try{
                        worker=new Worker(workerURL);this.worker=worker;this.reject=reject;
                        worker.onmessage=({data})=>{done();if(token!==this.generation){data.bitmap?.close();reject(new DOMException('Canceled','AbortError'));return;}if(data.error)reject(new Error(data.error));else resolve(data);};
                        worker.onerror=()=>{done();reject(new Error('Artwork worker could not start. Reload and try again.'));};
                        worker.postMessage({source:bitmap,processor,settings,options},[bitmap]);
                    }catch(error){bitmap.close();done();reject(error);}
                });
            }
            const opts={...options,cancelled:()=>token!==this.generation};
            if(processor==='analyze')return {analysis:await StudioProcessors.analyze(source,opts)};
            const output=processor==='halftone'?await HalftoneEngine.render(source,settings,opts):await StudioProcessors.render(source,processor,settings,opts);
            return output.canvas?output:{bitmap:output};
        }
    }
    class Preview {
        constructor(host,processor){this.host=host;this.processor=processor;this.job=new Job();this.generation=0;this.active=false;this.canvas=null;sessions.add(this);}
        cancel(redraw=false){const wasActive=this.active;this.generation++;clearTimeout(this.timer);clearTimeout(this.feedback);this.job.cancel();this.canvas=null;this.metadata=null;this.active=false;if(redraw&&wasActive)this.host.draw();}
        async render(source,settings,options){const result=await this.job.run(source,this.processor,settings,options);const raster=result.bitmap||result.canvas;if(!raster)return result;const canvas=document.createElement('canvas');canvas.width=raster.width;canvas.height=raster.height;canvas.getContext('2d').drawImage(raster,0,0);raster.close?.();return {...result,bitmap:undefined,canvas};}
        schedule(settings,view='result',options={}){
            for(const other of sessions)if(other!==this)other.cancel();
            this.cancel();if(!this.host.source()||this.host.busy())return;
            this.active=true;const token=this.generation;this.host.status('');
            if(view==='original'){this.host.draw();this.host.status('Original artwork — no changes applied.');return;}
            this.timer=setTimeout(async()=>{
                const source=this.host.source(),scale=Math.min(1,1000/Math.max(source.width,source.height));
                const prepared=this.host.previewSource?.();
                const small=prepared||document.createElement('canvas');if(!prepared){small.width=Math.max(1,Math.round(source.width*scale));small.height=Math.max(1,Math.round(source.height*scale));small.getContext('2d').drawImage(source,0,0,small.width,small.height);}
                this.feedback=setTimeout(()=>{if(token===this.generation)this.host.status('Processing artwork…');},250);
                try{
                    const result=await this.render(small,settings,{...options,documentWidth:source.width,documentHeight:source.height,view});
                    if(token!==this.generation)return;
                    this.canvas=result.canvas;this.metadata=result;this.host.result?.(result);this.host.draw();this.host.status(this.processor==='halftone'?'Halftone preview · Apply to keep changes.':'Preview · Apply to keep changes.');
                }catch(error){if(token===this.generation&&error.name!=='AbortError'){this.canvas=null;this.host.draw();this.host.status(error.message,true);}}
                finally{if(token===this.generation)clearTimeout(this.feedback);}
            },150);
        }
    }
    window.StudioPreview={Job,Preview,metadata(){for(const session of sessions)if(session.active&&session.canvas)return session.metadata;return null;},cancelAll(redraw=false){for(const session of sessions)session.cancel(redraw);},display(){for(const session of sessions)if(session.active&&session.canvas)return session.canvas;return null;}};
})();
