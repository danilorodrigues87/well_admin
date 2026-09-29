(function () {
  'use strict';

  function apiUrl() {
    return typeof wellAppUrl === 'function'
      ? wellAppUrl(null, (window.CRUD && window.CRUD.baseUrl) ? window.CRUD.baseUrl : '/painel/agendamentos')
      : (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '') + '/painel/agendamentos';
  }

  function csrf() {
    var el = document.querySelector('#form-agendar-rota input[name="_csrf"]')
      || document.querySelector('#crud-form input[name="_csrf"]')
      || document.querySelector('input[name="_csrf"]');
    return el ? el.value : '';
  }

  function boot() {
    var chk = document.getElementById('lote-alterar-prioridade');
    var selPri = document.getElementById('lote-prioridade');
    if (chk && selPri) {
      chk.addEventListener('change', function () {
        selPri.disabled = !chk.checked;
      });
    }

    var formRota = document.getElementById('form-agendar-rota');
    if (!formRota) return;

    formRota.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(formRota);
      fd.append('acao', 'agendar_rota');
      if (!fd.get('_csrf')) fd.append('_csrf', csrf());
      fetch(apiUrl(), { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            if (typeof Swal !== 'undefined') {
              Swal.fire({ title: 'Erro', text: data.message || 'Falha ao agendar.', icon: 'error' });
            }
            return;
          }
          if (typeof Swal !== 'undefined') {
            Swal.fire({ title: 'Ok', text: data.message, icon: 'success', timer: 2800, showConfirmButton: false });
          }
        })
        .catch(function () {
          if (typeof Swal !== 'undefined') {
            Swal.fire({ title: 'Erro', text: 'Falha de comunicação.', icon: 'error' });
          }
        });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
