/**
 * PWA — registro do service worker + banner instalar (Chrome/Edge/Android)
 */
(function () {
  'use strict';

  var DISMISS_KEY = 'well-pwa-install-dismiss';
  var deferredPrompt = null;

  function registerSw() {
    if (!('serviceWorker' in navigator)) {
      return;
    }
    var base = (typeof url_base !== 'undefined' ? url_base : '').replace(/\/+$/, '');
    var swUrl = base + '/sw.js';
    var scope = (base ? base + '/' : '/');
    navigator.serviceWorker.register(swUrl, { scope: scope })
      .catch(function (err) {
        console.warn('PWA SW:', err);
      });
  }

  function removeBanner() {
    var el = document.getElementById('well-pwa-install-banner');
    if (el) {
      el.remove();
    }
  }

  function showBanner() {
    if (localStorage.getItem(DISMISS_KEY) === '1') {
      return;
    }
    if (window.matchMedia('(display-mode: standalone)').matches) {
      return;
    }
    if (document.getElementById('well-pwa-install-banner')) {
      return;
    }

    var banner = document.createElement('div');
    banner.id = 'well-pwa-install-banner';
    banner.className = 'pwa-install-banner';
    banner.innerHTML =
      '<div class="pwa-install-inner">' +
      '<div><strong>Instalar Well Ops</strong><br><span class="small">Acesso rápido no celular, como app.</span></div>' +
      '<div class="d-flex gap-2 flex-wrap justify-content-center">' +
      '<button type="button" class="btn btn-light btn-sm" id="well-pwa-install-btn">Instalar</button>' +
      '<button type="button" class="btn btn-outline-light btn-sm" id="well-pwa-dismiss-btn">Agora não</button>' +
      '</div></div>';
    document.body.appendChild(banner);

    document.getElementById('well-pwa-dismiss-btn').addEventListener('click', function () {
      localStorage.setItem(DISMISS_KEY, '1');
      removeBanner();
    });

    document.getElementById('well-pwa-install-btn').addEventListener('click', function () {
      if (!deferredPrompt) {
        return;
      }
      deferredPrompt.prompt();
      deferredPrompt.userChoice.finally(function () {
        deferredPrompt = null;
        removeBanner();
      });
    });
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    showBanner();
  });

  window.addEventListener('DOMContentLoaded', registerSw);
})();
