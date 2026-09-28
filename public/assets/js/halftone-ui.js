'use strict';
(() => {
    window.createHalftoneUI=function(host){
        const $=id=>document.getElementById(id),engine=HalftoneEngine;
        let settings={...engine.defaults},valid=true;
        const panel=$('halftone-panel');
        const status=(text,error=false)=>{$('ht-status').textContent=text;$('ht-status').classList.toggle('error',error);if(session.active&&text)host.notify(text,error);};
        const session=new StudioPreview.Preview({...host,status},'halftone');
        function cancel(redraw=true){session.cancel(redraw);status('');}
        function dpi(){const value=host.dpi();return Number.isFinite(value)&&value>0?value:null;}
        function printInfo(){const source=host.source(),resolution=dpi();$('ht-unit').options[1].disabled=!resolution;
            $('ht-print').textContent=source?`Image: ${source.width.toLocaleString()} × ${source.height.toLocaleString()} px\n${resolution?`Print size: ${(source.width/resolution).toFixed(2)} × ${(source.height/resolution).toFixed(2)} in\nResolution: ${resolution} DPI (document export setting)\n`: 'Resolution: unknown\n'}Dot spacing: ${settings.spacing.toFixed(2)} px${resolution?' / '+(settings.spacing/resolution).toFixed(4)+' in':''}`:'Open an image to see print dimensions.';
        }
        function spacingFields(){const inches=$('ht-unit').value==='inches',factor=inches?dpi():1;
            for(const suffix of ['', '-range']){const el=$('ht-spacing'+suffix);el.min=3/factor;el.max=100/factor;el.step=inches?.0001:.1;el.value=settings.spacing/factor;}
        }
        function refresh(){
            $('halftone').disabled=!host.source()||host.busy()||!valid;
            $('ht-threshold').disabled=$('ht-threshold-range').disabled=!settings.thresholdEnabled;
            $('ht-background-label').hidden=settings.background!=='custom';printInfo();
        }
        function populate(){for(const key of Object.keys(engine.defaults)){const el=$('ht-'+key);if(el.type==='checkbox')el.checked=settings[key];else el.value=settings[key];const range=$('ht-'+key+'-range');if(range)range.value=settings[key];}spacingFields();valid=true;refresh();}
        function schedule(){
            valid=true;try{settings=engine.validate(settings);}catch(error){valid=false;session.cancel(true);status(error.message,true);refresh();return;}
            refresh();session.schedule(settings,$('ht-preview').value==='original'?'original':'result');
        }
        for(const key of Object.keys(engine.defaults)){
            const field=$('ht-'+key),range=$('ht-'+key+'-range');
            const change=event=>{
                const el=event.target;let value=field.type==='checkbox'?field.checked:el.value;
                if(engine.ranges[key]){
                    value=value===''?NaN:Number(value);if(key==='spacing'&&$('ht-unit').value==='inches')value*=dpi();
                    if(Number.isFinite(value)){const [min,max]=engine.ranges[key];value=Math.min(max,Math.max(min,value));}
                }
                settings[key]=value;const display=key==='spacing'&&$('ht-unit').value==='inches'?value/dpi():value;
                if(range&&Number.isFinite(value)){range.value=display;field.value=display;}
                $('ht-preset').value='custom';schedule();
            };
            field.addEventListener('input',change);if(range)range.addEventListener('input',change);
        }
        $('ht-preset').onchange=()=>{const preset=$('ht-preset').value;if(preset==='custom')return;settings={...engine.defaults,...engine.presets[preset]};populate();schedule();};
        $('ht-unit').onchange=()=>{spacingFields();printInfo();};
        $('ht-preview').onchange=schedule;
        $('ht-reset').onclick=()=>{settings={...engine.defaults};$('ht-preset').value='standard';populate();schedule();};
        $('ht-reset-dots').onclick=()=>{for(const key of ['shape','spacing','maxSize','minSize','angle'])settings[key]=engine.defaults[key];$('ht-preset').value='custom';populate();schedule();};
        $('ht-cancel').onclick=()=>{cancel();status('Preview canceled. Image unchanged.');host.notify('Halftone preview canceled. Image unchanged.');};
        panel.addEventListener('toggle',()=>{if(host.busy())return;if(panel.open)schedule();else cancel();});
        $('halftone').onclick=()=>{
            if(!valid||host.busy()||!host.source())return;
            host.commit(async()=>{
                const source=host.source();
                status('Rendering full-resolution halftone…');
                const result=await session.render(source,settings,{documentWidth:source.width,documentHeight:source.height});
                const out=result.canvas;
                out.halftoneInfo=settings.background==='transparent'?'Halftone on transparency'+(settings.clip?' · clipped to source alpha.':'.'):'Halftone with '+(settings.background==='white'?'white':'custom')+' background.';
                status('Halftone applied at full resolution.');return out;
            });
        };
        populate();
        return {cancel,refresh,display:()=>session.active?session.canvas:null,documentChanged(){cancel(false);refresh();},dpiChanged(){spacingFields();refresh();if(session.active)schedule();}};
    };
})();
