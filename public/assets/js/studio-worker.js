'use strict';
importScripts('studio-image.js?v=20260924-print','halftone-engine.js?v=20260924-print','studio-processors.js?v=20260924-local-ai');
self.onmessage=async({data})=>{
    try{
        const options=data.options||{};
        if(data.processor==='analyze'){const analysis=await StudioProcessors.analyze(data.source,options);data.source.close();self.postMessage({analysis});return;}
        const result=data.processor==='halftone'?{canvas:await HalftoneEngine.render(data.source,data.settings,options)}:await StudioProcessors.render(data.source,data.processor,data.settings,options);
        data.source.close();const bitmap=result.canvas.transferToImageBitmap();const {canvas,...metadata}=result;self.postMessage({...metadata,bitmap},[bitmap]);
    }catch(error){data.source.close();self.postMessage({error:error.message});}
};
