/**
 * Well Admin — comportamento mobile (sidebar, tabelas CRUD, modais)
 */
(function () {
  'use strict';

  var MQ_TABLET = window.matchMedia('(max-width: 991.98px)');
  var MQ_MOBILE = window.matchMedia('(max-width: 767.98px)');

  function closeSidebar() {
    document.body.classList.remove('sb-sidenav-toggled');
    try {
      localStorage.setItem('sb|sidebar-toggle', 'false');
    } catch (e) { /* ignore */ }
  }

  function initSidebarMobile() {
    var nav = document.querySelector('#layoutSidenav_nav');
    if (!nav) {
      return;
    }

    nav.querySelectorAll('a.nav-link[href]').forEach(function (link) {
      link.addEventListener('click', function () {
        if (!MQ_TABLET.matches) {
          return;
        }
        /* Grupos do menu (Operação, Cadastros…) usam href="#" + collapse — não fechar o drawer */
        if (link.getAttribute('data-bs-toggle') === 'collapse') {
          return;
        }
        var href = (link.getAttribute('href') || '').trim();
        if (href === '' || href === '#') {
          return;
        }
        closeSidebar();
      });
    });

    var content = document.querySelector('#layoutSidenav_content');
    var main = document.querySelector('#layoutSidenav_content main');
    if (content && main) {
      content.addEventListener('click', function (ev) {
        if (!MQ_TABLET.matches || !document.body.classList.contains('sb-sidenav-toggled')) {
          return;
        }
        if (main.contains(ev.target)) {
          closeSidebar();
        }
      });
    }

    if (MQ_TABLET.matches) {
      closeSidebar();
    }
  }

  function wrapTablesScroll() {
    document.querySelectorAll('.card-body table.table').forEach(function (table) {
      if (table.closest('.table-responsive') || table.closest('.well-no-mobile-enhance')) {
        return;
      }
      var wrap = document.createElement('div');
      wrap.className = 'table-responsive well-table-scroll';
      table.parentNode.insertBefore(wrap, table);
      wrap.appendChild(table);
    });
  }

  function labelForTable(table) {
    var headers = [];
    table.querySelectorAll('thead th').forEach(function (th, idx) {
      headers[idx] = (th.textContent || '').trim() || '—';
    });
    if (!headers.length) {
      return;
    }

    table.querySelectorAll('tbody tr').forEach(function (tr) {
      tr.querySelectorAll('td').forEach(function (td, idx) {
        if (!td.getAttribute('data-label')) {
          td.setAttribute('data-label', headers[idx] || '—');
        }
        if (/ações|acao|ações/i.test(headers[idx] || '') || td.querySelector('.btn')) {
          td.classList.add('well-td-actions');
        }
      });
    });
  }

  function enhanceCrudTables() {
    if (!MQ_MOBILE.matches) {
      return;
    }
    document.querySelectorAll('.card-body table.table').forEach(function (table) {
      table.classList.add('well-table-enhanced', 'well-table-mobile-cards');
      labelForTable(table);
    });
  }

  function upgradeModals() {
    if (!window.matchMedia('(max-width: 575.98px)').matches) {
      return;
    }
    document.querySelectorAll('.modal .modal-dialog').forEach(function (dialog) {
      if (!dialog.classList.contains('modal-fullscreen-sm-down')) {
        dialog.classList.add('modal-fullscreen-sm-down');
      }
    });
  }

  function onResize() {
    if (!MQ_MOBILE.matches) {
      document.querySelectorAll('.well-table-mobile-cards').forEach(function (table) {
        table.classList.remove('well-table-mobile-cards');
      });
    } else {
      enhanceCrudTables();
    }
  }

  function initColetorDockActive() {
    var dock = document.querySelector('.well-coletor-dock');
    if (!dock) {
      return;
    }
    var slug = document.body.getAttribute('data-current-module') || '';
    var path = window.location.pathname.replace(/\/+$/, '');
    dock.querySelectorAll('a[data-module]').forEach(function (a) {
      var mod = a.getAttribute('data-module');
      if (mod && mod === slug) {
        a.classList.add('active');
        return;
      }
      var href = a.getAttribute('href') || '';
      if (href && path.indexOf(href.replace(/^https?:\/\/[^/]+/, '').replace(/\/+$/, '')) !== -1) {
        a.classList.add('active');
      }
    });
  }

  window.addEventListener('DOMContentLoaded', function () {
    initSidebarMobile();
    wrapTablesScroll();
    enhanceCrudTables();
    upgradeModals();
    initColetorDockActive();
  });

  window.addEventListener('resize', function () {
    onResize();
  });

  document.addEventListener('well-crud-listed', function () {
    wrapTablesScroll();
    enhanceCrudTables();
  });
})();
