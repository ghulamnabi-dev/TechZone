<?php
/**
 * Boot guard + loader + navigation bar, shared by the storefront and the admin.
 *
 * Call tz_boot_ui('store' | 'admin') inside <head>, BEFORE the stylesheets, and mark the
 * stylesheets the page cannot render without with `data-tz-critical`.
 *
 * What it does:
 *  - Keeps <body> hidden until every critical stylesheet has actually loaded and parsed
 *    (same-origin sheets must expose CSS rules; an empty/truncated sheet counts as failed).
 *  - Re-fetches a failed critical stylesheet up to twice, with a cache-buster.
 *  - Shows the TZ loader only if that takes longer than 250 ms, so fast loads never flash.
 *  - Never blocks: the page is revealed after 2.5 s whatever happens.
 *  - Shows a thin signal-yellow progress bar while navigating to another page or submitting
 *    a form, cleared on pageshow (back/forward cache) or after 10 s.
 *  - Respects prefers-reduced-motion and works without any other script or stylesheet.
 *
 * This file only emits markup; it has no side effects when included.
 */

if (!function_exists('tz_boot_ui')) {
    function tz_boot_ui(string $theme = 'store'): void
    {
        $bg = $theme === 'admin' ? '#f4f5f7' : '#ffffff';
        ?>
<style id="tz-boot-css">
html.tz-boot{background:<?= $bg ?>}
html.tz-boot body{visibility:hidden}
html.tz-boot::before,html.tz-boot::after{position:fixed;left:50%;top:50%;z-index:2147483647;opacity:0;animation:tz-boot-in .2s ease-out .25s forwards;pointer-events:none}
html.tz-boot::before{content:"";width:56px;height:56px;margin:-40px 0 0 -28px;box-sizing:border-box;border:3px solid #000;background:linear-gradient(#ffd400,#ffd400) no-repeat bottom/100% 0;animation:tz-boot-in .2s ease-out .25s forwards,tz-boot-fill 1.1s cubic-bezier(.5,0,.2,1) .25s infinite}
html.tz-boot::after{content:"TZ";width:56px;margin:-40px 0 0 -28px;line-height:56px;text-align:center;font:800 22px/56px "Archivo","Arial Narrow",Arial,sans-serif;font-stretch:75%;letter-spacing:-.01em;color:#000}
@keyframes tz-boot-in{to{opacity:1}}
@keyframes tz-boot-fill{0%{background-size:100% 0}55%{background-size:100% 100%}100%{background-size:100% 100%;background-position:bottom}}
html.tz-nav body::before{content:"";position:fixed;top:0;left:0;height:3px;width:0;background:#ffd400;box-shadow:0 1px 0 rgba(0,0,0,.12);z-index:2147483647;animation:tz-nav-bar 9s cubic-bezier(.08,.8,.2,1) forwards}
@keyframes tz-nav-bar{from{width:0}to{width:88%}}
@media (prefers-reduced-motion:reduce){html.tz-boot::before{animation:tz-boot-in .01s linear .25s forwards;background-size:100% 100%}html.tz-nav body::before{animation:none;width:88%}}
</style>
<script id="tz-boot-js">
(function () {
  var d = document.documentElement, done = false, MAX = 2500;
  d.classList.add('tz-boot');
  function crit() { return [].slice.call(document.querySelectorAll('link[rel="stylesheet"][data-tz-critical]')); }
  function ok(l) {
    if (!l.sheet) return false;
    try { return l.sheet.cssRules.length > 0; }
    catch (e) {
      /* Cross-origin sheets (CDN) hide their rules: present = loaded. A same-origin sheet that
         throws is a failed load (Chromium reports failed links this way). */
      try { return new URL(l.href, location.href).origin !== location.origin; } catch (x) { return false; }
    }
  }
  function retry(l) {
    var n = +(l.getAttribute('data-tz-retry') || 0);
    if (n >= 2 || l.getAttribute('data-tz-replaced')) return;
    l.setAttribute('data-tz-replaced', '1');
    l.removeAttribute('data-tz-critical');
    var c = document.createElement('link');
    c.rel = 'stylesheet';
    c.setAttribute('data-tz-critical', '');
    c.setAttribute('data-tz-retry', String(n + 1));
    c.href = l.href + (l.href.indexOf('?') > -1 ? '&' : '?') + 'tzr=' + (n + 1) + '-' + Date.now();
    c.onload = check;
    c.onerror = function () { retry(c); check(); };
    l.parentNode.insertBefore(c, l.nextSibling);
  }
  function finish() {
    if (done) return;
    done = true;
    d.classList.remove('tz-boot');
  }
  function check() {
    if (done || document.readyState === 'loading') return;
    var ls = crit();
    if (ls.every(function (l) { return ok(l) || +(l.getAttribute('data-tz-retry') || 0) >= 2 && l.sheet; })) finish();
  }
  window.addEventListener('error', function (e) {
    var l = e.target;
    if (l && l.tagName === 'LINK' && l.hasAttribute('data-tz-critical')) { retry(l); check(); }
  }, true);
  document.addEventListener('DOMContentLoaded', function () {
    crit().forEach(function (l) {
      if (l.sheet && !ok(l)) retry(l);                 /* loaded but empty or truncated */
      else if (!l.sheet) l.addEventListener('load', function () { if (!ok(l)) retry(l); check(); });
    });
    check();
  });
  window.addEventListener('load', function () {
    crit().forEach(function (l) { if (!ok(l)) retry(l); });
    check();
    setTimeout(finish, 1200);
  });
  setTimeout(finish, MAX);

  /* navigation progress bar */
  var navTimer = null;
  function navStart() {
    d.classList.add('tz-nav');
    clearTimeout(navTimer);
    navTimer = setTimeout(function () { d.classList.remove('tz-nav'); }, 10000);
  }
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-bs-toggle')) return;
    var raw = a.getAttribute('href') || '';
    if (!raw || raw.charAt(0) === '#' || /^(javascript|mailto|tel|sms|whatsapp):/i.test(raw)) return;
    var u; try { u = new URL(a.href, location.href); } catch (x) { return; }
    if (u.origin !== location.origin) return;
    if (u.pathname === location.pathname && u.search === location.search && u.hash) return;
    navStart();
  });
  document.addEventListener('submit', function (e) {
    var f = e.target;
    setTimeout(function () {
      if (!e.defaultPrevented && f && (f.getAttribute('target') || '') !== '_blank') navStart();
    }, 0);
  });
  window.addEventListener('pageshow', function () { d.classList.remove('tz-nav'); });
})();
</script>
<?php
    }
}
