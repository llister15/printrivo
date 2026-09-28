(() => {
  const script = document.currentScript;
  const id = script?.dataset.measurementId;
  if (!/^G-[A-Z0-9]{5,20}$/.test(id || '')) return;
  const banner = document.getElementById('analytics-consent');
  const settings = document.getElementById('analytics-settings');
  const key = 'printrivo.analytics-consent.v1';
  let enabled = false;
  let loaded = false;
  const save = choice => {
    try { localStorage.setItem(key, JSON.stringify({choice, expires: Date.now() + 180 * 86400000})); } catch (_) {}
  };
  const consent = () => {
    try { const saved = JSON.parse(localStorage.getItem(key)); return saved?.expires > Date.now() ? saved.choice : null; } catch (_) { return null; }
  };
  function accept() {
    enabled = true;
    window['ga-disable-' + id] = false;
    if (loaded) return;
    loaded = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('consent', 'default', {analytics_storage: 'granted', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied'});
    window.gtag('js', new Date());
    window.gtag('config', id, {
      send_page_view: false,
      page_location: script.dataset.pageUrl,
      page_referrer: '',
      page_title: document.title,
      allow_google_signals: false,
      allow_ad_personalization_signals: false,
      cookie_expires: 15552000
    });
    window.gtag('event', 'page_view', {page_location: script.dataset.pageUrl, page_referrer: '', page_title: document.title});
    const tag = document.createElement('script');
    tag.async = true;
    tag.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    tag.referrerPolicy = 'no-referrer';
    document.head.appendChild(tag);
  }
  function decline() {
    enabled = false;
    window['ga-disable-' + id] = true;
    // Remove GA cookies at host/parent domain scopes; no request to Google is needed.
    const parts = location.hostname.split('.');
    document.cookie.split(';').forEach(cookie => {
      const name = cookie.trim().split('=')[0];
      if (name !== '_ga' && !name.startsWith('_ga_')) return;
      document.cookie = name + '=; Max-Age=0; path=/';
      for (let i = 0; i < parts.length - 1; i++) {
        document.cookie = name + '=; Max-Age=0; path=/; domain=.' + parts.slice(i).join('.');
      }
    });
  }
  settings.addEventListener('click', () => { banner.hidden = false; document.getElementById('analytics-accept').focus(); });
  document.getElementById('analytics-accept').addEventListener('click', () => { save('granted'); banner.hidden = true; accept(); settings.focus(); });
  document.getElementById('analytics-decline').addEventListener('click', () => { save('denied'); decline(); banner.hidden = true; settings.focus(); });
  window.addEventListener('storage', event => {
    if (event.key !== key) return;
    if (consent() === 'granted') accept(); else { decline(); banner.hidden = consent() === 'denied'; }
  });
  const saved = consent();
  if (saved === 'granted') accept(); else { decline(); banner.hidden = saved === 'denied'; }
  // Fixed event names/labels only: never transmit form fields or arbitrary link URLs.
  document.addEventListener('click', event => {
    if (!enabled || !event.target.closest) return;
    const link = event.target.closest('a');
    if (link && new URL(link.href).pathname.endsWith('/register')) window.gtag('event', 'trial_cta_click');
    const demo = event.target.closest('[data-preview]');
    if (demo && ['gang-sheet', 'image-studio', 'qr'].includes(demo.dataset.preview)) window.gtag('event', 'tool_demo_select', {tool: demo.dataset.preview});
  });
})();
