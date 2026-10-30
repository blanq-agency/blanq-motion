/* ==========================================================
   blanq Motion – Bricksmotion-Runtime
   Ersetzt den "BRICKSMOTION LOADER", der sonst in jeder
   Bricksmotion-Komponente steckt. GSAP, seine Plugins und Lenis
   lädt blanq Motion lokal und vorher; diese Datei stellt nur noch
   bereit, was die Komponenten aufrufen:
     BM.ready, BM.load, BM.unlazy, BM.reducedMotion
   und startet Lenis (außer bei <html data-bm-lenis="off">,
   window.BM_LENIS = false oder "Bewegung reduzieren").
   Komponenten, die den alten Loader noch enthalten, nutzen
   automatisch diese Runtime (gleiche Versionsnummer 3.2).
   Im Bricks-Editor ist GSAP nicht geladen: BM.load wartet dann
   still, die Komponenten bleiben statisch und werfen keinen Fehler.
   ========================================================== */
(function () {
  var BM = (window.BM = window.BM || {});
  if (BM.v && BM.v >= 3.2) return;
  BM.v = 3.2;
  BM.runtime = 'blanq-motion';

  BM.reducedMotion = function () {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  };

  BM.ready = BM.ready || function (fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  };

  // Bricks-Lazy-Bilder sofort laden (nötig, wenn Bilder per transform bewegt werden).
  BM.unlazy = BM.unlazy || function (scope) {
    scope.querySelectorAll('img[data-src]').forEach(function (img) {
      if (img.dataset.srcset) img.srcset = img.dataset.srcset;
      if (img.dataset.sizes) img.sizes = img.dataset.sizes;
      img.src = img.dataset.src;
      img.removeAttribute('data-src');
      img.classList.remove('bricks-lazy-hidden');
    });
  };

  // ScrollTrigger misst nur bei load und resize. Lazy-Bilder, späte Webfonts und
  // Performance-Plugins (content-visibility) ändern die Seitenhöhe danach:
  // dann neu messen, entprellt. Ein Observer pro Seite.
  BM.watchLayout = function () {
    if (BM.layoutWatch || !window.ResizeObserver || !window.ScrollTrigger || !document.body) return;
    var body = document.body, h = 0, vw = 0, vh = 0, timer;
    function sync() { h = body.offsetHeight; vw = window.innerWidth; vh = window.innerHeight; }
    sync();
    ScrollTrigger.addEventListener('refresh', sync);
    function check() {
      if (window.innerWidth !== vw || window.innerHeight !== vh) return sync();
      if (Math.abs(body.offsetHeight - h) > 1) ScrollTrigger.refresh();
    }
    BM.layoutWatch = new ResizeObserver(function () {
      clearTimeout(timer);
      timer = setTimeout(check, 200);
    });
    BM.layoutWatch.observe(body);
  };

  function lenisOff() {
    return window.BM_LENIS === false || document.documentElement.getAttribute('data-bm-lenis') === 'off';
  }

  function startLenis() {
    if (BM.lenis || window.lenis || lenisOff() || BM.reducedMotion() || !window.Lenis || !window.ScrollTrigger) return;
    var style = document.createElement('style');
    style.textContent = 'html.lenis,html.lenis body{height:auto}' +
      '.lenis:not(.lenis-autoToggle).lenis-stopped{overflow:clip}' +
      '.lenis [data-lenis-prevent]{overscroll-behavior:contain}' +
      '.lenis.lenis-smooth iframe{pointer-events:none}';
    document.head.appendChild(style);
    var lenis = new Lenis({ autoRaf: false });
    lenis.on('scroll', ScrollTrigger.update);
    ScrollTrigger.addEventListener('refresh', function () { lenis.resize(); });
    gsap.ticker.add(function (time) { lenis.raf(time * 1000); });
    gsap.ticker.lagSmoothing(0);
    BM.lenis = lenis;
  }

  var told = false;
  // Die Bibliotheken sind schon da: nur Plugins registrieren und Lenis starten.
  BM.load = function (plugins) {
    if (!window.gsap) {
      if (!told) {
        told = true;
        console.info('blanq Motion: GSAP ist hier nicht geladen (z. B. im Bricks-Editor), Bricksmotion-Komponenten bleiben statisch.');
      }
      return new Promise(function () {});
    }
    var names = ['ScrollTrigger'].concat(plugins || []);
    var found = [];
    names.forEach(function (n) {
      var lib = window[n] || window[n + 'Plugin'];
      if (lib) found.push(lib);
      else console.warn('blanq Motion: GSAP-Plugin "' + n + '" wurde nicht geladen.');
    });
    gsap.registerPlugin.apply(gsap, found);
    startLenis();
    BM.watchLayout();
    return Promise.resolve();
  };
})();
