(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var chk = document.getElementById('lote-alterar-prioridade');
    var sel = document.getElementById('lote-prioridade');
    if (chk && sel) {
      chk.addEventListener('change', function () {
        sel.disabled = !chk.checked;
      });
    }

    var form = document.getElementById('form-agendar-rota');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(form);
      fd.append('acao', 'agendar_rota');
      if (!fd.get('_csrf')) {
        var csrf = document.querySelector('#crud-form input[name="_csrf"]');
        if (csrf) fd.append('_csrf', csrf.value);
      }

      var url = typeof wellAppUrl === 'function'
        ? wellAppUrl(null, (window.CRUD && window.CRUD.baseUrl) ? window.CRUD.baseUrl : '/painel/agendamentos')
        : (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '') + '/painel/agendamentos';
      fetch(url, {
        method: 'POST',
        body: fd
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data.success) {
          if (typeof Swal !== 'undefined') {
            Swal.fire({ title: 'Erro', text: data.message || 'Falha ao agendar.', icon: 'error' });
          } else {
            alert(data.message || 'Falha ao agendar.');
          }
          return;
        }
        if (typeof Swal !== 'undefined') {
          Swal.fire({ title: 'Ok', text: data.message, icon: 'success', timer: 2800, showConfirmButton: false });
        }
        if (typeof carregarLista === 'function') {
          carregarLista(1);
        }
      }).catch(function () {
        if (typeof Swal !== 'undefined') {
          Swal.fire({ title: 'Erro', text: 'Falha de comunicação.', icon: 'error' });
        }
      });
    });
  });
})();
