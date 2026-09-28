(() => {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const image = document.getElementById('showcase-image');
  const link = document.querySelector('.hero-screenshot-link');
  const previews = {
    'gang-sheet': ['Printrivo Gang Sheet Builder', 'Animated demonstration of Printrivo Gang Sheet Builder', 'Printrivo_GangsheetBuilder_Demo.gif'],
    'image-studio': ['Printrivo Image Studio', 'Animated demonstration of Printrivo Image Studio', 'Printrivo_Image_Studio_Demo.gif'],
    qr: ['Printrivo QR Generator', 'Animated demonstration of Printrivo QR Generator', 'Printrivo_QR_Generator_Demo.gif']
  };
  document.querySelectorAll('.showcase-choice').forEach(button => {
    button.addEventListener('click', () => {
      const [name, description, file] = previews[button.dataset.preview];
      const next = new URL(file || encodeURIComponent(name) + '.png', image.src).href;
      image.src = next;
      image.alt = description;
      link.href = next;
      link.setAttribute('aria-label', `Enlarge ${name} ${file ? 'demo' : 'screenshot'} (opens in a new tab)`);
      document.querySelectorAll('.showcase-choice').forEach(choice => choice.setAttribute('aria-pressed', String(choice === button)));
      if (!reducedMotion.matches && image.animate) image.animate([{opacity: .4}, {opacity: 1}], {duration: 280});
    });
  });
  // Content remains visible if JavaScript, animation or the observer is unavailable.
  if ('IntersectionObserver' in window && !reducedMotion.matches) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('sales-revealed');
        observer.unobserve(entry.target);
      });
    }, {threshold: .12});
    document.querySelectorAll('.sales-page .feature-card, .sales-page .workflow-grid, .sales-page .offer-grid, .sales-page .faq-section, .sales-page .production-pain-grid article, .sales-page .audience-card-grid article').forEach(node => observer.observe(node));
  }
})();
