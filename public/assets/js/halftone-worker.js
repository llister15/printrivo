'use strict';
importScripts('halftone-engine.js?v=20260921-halftone');
self.onmessage=async({data})=>{
    try{
        const out=await HalftoneEngine.render(data.source,data.settings,data.options);
        data.source.close();const bitmap=out.transferToImageBitmap();self.postMessage({bitmap},[bitmap]);
    }catch(error){data.source.close();self.postMessage({error:error.message});}
};
