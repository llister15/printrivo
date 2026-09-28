(() => {
    const button = document.getElementById('printrivo-back-to-top');
    if (!button) return;
    let frame;
    function update() { button.hidden = window.scrollY < 400; frame = null; }
    window.addEventListener('scroll', () => { if (!frame) frame = requestAnimationFrame(update); }, {passive:true});
    window.addEventListener('pageshow', update);
    update();
    button.addEventListener('click', () => {
        const target = document.querySelector('header a[href]');
        target?.focus({preventScroll:true});
        window.scrollTo({top:0, behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
    });
})();
