/**
 * Tema claro/escuro — Well Eco Admin.
 * Padrão: prefers-color-scheme do sistema (se usuário não escolheu manualmente).
 */
(function () {
  'use strict';

  var KEY = 'well-eco-theme';

  function systemTheme() {
    try {
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    } catch (e) {
      return 'light';
    }
  }

  function storedTheme() {
    try {
      var t = localStorage.getItem(KEY);
      return t === 'dark' || t === 'light' ? t : null;
    } catch (e) {
      return null;
    }
  }

  function current() {
    return storedTheme() || systemTheme();
  }

  function apply(theme, persist) {
    var t = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-bs-theme', t);
    if (persist) {
      try {
        localStorage.setItem(KEY, t);
      } catch (e) {}
    }
    syncToggleLabel(t);
    try {
      document.dispatchEvent(new CustomEvent('painel-theme-change', { detail: { theme: t } }));
    } catch (e) {}
  }

  function syncToggleLabel(theme) {
    var el = document.getElementById('btn-toggle-theme');
    if (!el) return;
    var dark = theme === 'dark';
    el.innerHTML = dark
      ? '<i class="fa-regular fa-sun me-1"></i> Tema claro'
      : '<i class="fa-regular fa-moon me-1"></i> Tema escuro';
  }

  function toggle() {
    apply(current() === 'dark' ? 'light' : 'dark', true);
  }

  function bindSystemListener() {
    try {
      var mq = window.matchMedia('(prefers-color-scheme: dark)');
      mq.addEventListener('change', function () {
        if (!storedTheme()) {
          apply(systemTheme(), false);
        }
      });
    } catch (e) {}
  }

  document.addEventListener('DOMContentLoaded', function () {
    syncToggleLabel(current());
    bindSystemListener();
    var el = document.getElementById('btn-toggle-theme');
    if (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        toggle();
      });
    }
  });

  window.PainelTheme = {
    apply: function (theme) { apply(theme, true); },
    toggle: toggle,
    current: current,
    resolveInitial: current,
  };
})();
