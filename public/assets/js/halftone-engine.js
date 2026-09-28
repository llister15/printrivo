'use strict';
/* Shared by the worker and cooperative browser fallback. Coordinates and settings
 * stay in document pixels; only preview rasterization changes resolution. */
(() => {
    const defaults = Object.freeze({shape:'circle',spacing:12,maxSize:100,minSize:0,angle:45,mode:'dark',brightness:0,contrast:0,gamma:1,threshold:128,thresholdEnabled:false,ink:'#000000',background:'transparent',backgroundColor:'#ffffff',quality:'balanced',softness:0,gain:0,preserve:true,clip:true,removeTiny:true,minPrintable:1,offsetX:0,offsetY:0});
    const ranges={spacing:[3,100],maxSize:[10,150],minSize:[0,100],angle:[0,180],brightness:[-100,100],contrast:[-100,100],gamma:[.1,3],threshold:[0,255],softness:[0,100],gain:[-50,50],minPrintable:[0,100],offsetX:[-100,100],offsetY:[-100,100]};
    const presets={fine:{spacing:6,maxSize:90},standard:{},bold:{spacing:20,maxSize:115,contrast:15},vintage:{spacing:24,angle:45,contrast:20,gain:8},'high-contrast':{spacing:12,contrast:55,thresholdEnabled:true,threshold:128}};
    function validate(input) {
        const s={...defaults,...input};
        for(const [key,[min,max]] of Object.entries(ranges)){
            if(typeof s[key]!=='number'||!Number.isFinite(s[key]))throw new Error('Enter a valid '+key+' value.');
            s[key]=Math.max(min,Math.min(max,s[key]));
        }
        if(s.minSize>s.maxSize)throw new Error('Minimum dot size must not exceed maximum dot size.');
        for(const [key,values] of Object.entries({shape:['circle','ellipse','square','diamond','line'],mode:['dark','light','invert'],quality:['fast','balanced','high'],background:['transparent','white','custom']}))if(!values.includes(s[key]))throw new Error('Invalid '+key+'.');
        for(const key of ['ink','backgroundColor'])if(!/^#[\da-f]{6}$/i.test(s[key]))throw new Error('Choose a valid color.');
        for(const key of ['thresholdEnabled','preserve','clip','removeTiny'])if(typeof s[key]!=='boolean')throw new Error('Invalid '+key+'.');
        return s;
    }
    const clamp=v=>Math.max(0,Math.min(1,v));
    function coverage(l,s){
        l=clamp((l+s.brightness/100-.5)*Math.pow(2,s.contrast/25)+.5);
        let ink=s.mode==='dark'?1-l:l; // Invert reverses the default dark-artwork screen.
        ink=Math.pow(clamp(ink),1/s.gamma);
        if(s.thresholdEnabled)ink=ink>=s.threshold/255?1:0;
        return clamp(ink+s.gain/100);
    }
    const shapes={
        circle(c,d){c.arc(0,0,d/2,0,Math.PI*2);},
        ellipse(c,d){c.ellipse(0,0,d*.65,d/2.6,0,0,Math.PI*2);},
        square(c,d){c.rect(-d/2,-d/2,d,d);},
        diamond(c,d){c.moveTo(0,-d/Math.SQRT2);c.lineTo(d/Math.SQRT2,0);c.lineTo(0,d/Math.SQRT2);c.lineTo(-d/Math.SQRT2,0);c.closePath();},
        line(c,d,spacing,fraction){c.rect(-spacing/2,-d*fraction/2,spacing,d*fraction);}
    };
    function makeCanvas(w,h){const c=typeof OffscreenCanvas!=='undefined'?new OffscreenCanvas(w,h):document.createElement('canvas');c.width=w;c.height=h;return c;}
    async function render(source,input,{documentWidth=source.width,documentHeight=source.height,cancelled=()=>false}={}){
        const s=validate(input),w=source.width,h=source.height,scale=w/documentWidth;
        if(!w||!h||documentWidth>30000||documentHeight>30000||documentWidth*documentHeight>256000000)throw new Error('Unsupported image dimensions.');
        const src=makeCanvas(w,h),sc=src.getContext('2d',{willReadFrequently:true});sc.drawImage(source,0,0);
        const pixels=sc.getImageData(0,0,w,h).data,out=makeCanvas(w,h),c=out.getContext('2d');
        const rad=s.angle*Math.PI/180,cos=Math.cos(rad),sin=Math.sin(rad),spacing=Math.max(s.spacing,scale<1?2/scale:s.spacing);
        // A subpixel screen cannot be represented faithfully at fit zoom. Preview
        // uses a resolvable grid; final rendering always uses the exact spacing.
        // Bound the rotated document, rather than using a diagonal square grid.
        const corners=[[0,0],[documentWidth,0],[0,documentHeight],[documentWidth,documentHeight]].map(([x,y])=>[x*cos+y*sin,-x*sin+y*cos]);
        const minU=Math.min(...corners.map(p=>p[0])),maxU=Math.max(...corners.map(p=>p[0])),minV=Math.min(...corners.map(p=>p[1])),maxV=Math.max(...corners.map(p=>p[1]));
        const startU=Math.floor((minU-s.offsetX)/spacing)-1,endU=Math.ceil((maxU-s.offsetX)/spacing)+1;
        const startV=Math.floor((minV-s.offsetY)/spacing)-1,endV=Math.ceil((maxV-s.offsetY)/spacing)+1;
        const count=s.quality==='fast'?2:s.quality==='balanced'?4:Math.min(16,Math.max(4,Math.ceil(spacing*scale)));
        let lastYield=performance.now();
        c.fillStyle=s.ink;
        for(let gy=startV;gy<=endV;gy++){
            for(let gx=startU;gx<=endU;gx++){
                const u=(gx+.5)*spacing+s.offsetX,v=(gy+.5)*spacing+s.offsetY,x=u*cos-v*sin,y=u*sin+v*cos;
                if(x < -spacing*2||y < -spacing*2||x>documentWidth+spacing*2||y>documentHeight+spacing*2)continue;
                let sum=0,weight=0,alpha=0,samples=0;
                // Stratified area sampling in the rotated cell. Alpha-weighted RGB
                // ignores hidden RGB in transparent PNGs and retains partial edges.
                for(let sy=0;sy<count;sy++)for(let sx=0;sx<count;sx++){
                    const du=((sx+.5)/count-.5)*spacing,dv=((sy+.5)/count-.5)*spacing;
                    const px=Math.floor((x+du*cos-dv*sin)*scale),py=Math.floor((y+du*sin+dv*cos)*h/documentHeight);
                    if(px<0||py<0||px>=w||py>=h)continue;
                    const i=(py*w+px)*4,a=pixels[i+3]/255,l=(.2126*pixels[i]+.7152*pixels[i+1]+.0722*pixels[i+2])/255;
                    samples++;alpha+=a;const aw=s.preserve?a:1;sum+=l*aw;weight+=aw;
                }
                if(!weight||!samples)continue;
                const ink=coverage(sum/weight,s);if(ink<=0)continue;
                const fraction=Math.sqrt(ink),d=spacing*(s.minSize+(s.maxSize-s.minSize)*fraction)/100;
                const printable=s.shape==='line'?d*fraction:s.shape==='ellipse'?d/1.3:d;
                if(s.removeTiny&&printable<s.minPrintable)continue;
                c.save();c.translate(x*scale,y*h/documentHeight);c.rotate(rad);
                // Clipping later applies alpha exactly once. Without clipping, use
                // cell alpha to preserve partially transparent artwork.
                c.globalAlpha=s.preserve&&!s.clip?alpha/samples:1;
                if(s.softness)c.filter=`blur(${s.softness/100*Math.min(spacing*.12,3)*scale}px)`;
                c.beginPath();shapes[s.shape](c,d*scale,spacing*scale,fraction);c.fill();c.restore();
            }
            if(performance.now()-lastYield>12){await new Promise(r=>setTimeout(r,0));if(cancelled())throw new DOMException('Render cancelled','AbortError');lastYield=performance.now();}
        }
        if(cancelled())throw new DOMException('Render cancelled','AbortError');
        if(s.clip){c.globalCompositeOperation='destination-in';c.drawImage(src,0,0);c.globalCompositeOperation='source-over';}
        if(s.background!=='transparent'){c.globalCompositeOperation='destination-over';c.fillStyle=s.background==='white'?'#ffffff':s.backgroundColor;c.fillRect(0,0,w,h);c.globalCompositeOperation='source-over';}
        return out;
    }
    globalThis.HalftoneEngine={defaults,ranges,presets,validate,coverage,shapes,render};
})();
