(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-nova-solic');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(form);
        fd.append('acao', 'criar');
        fetch(window.location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!data.success) {
              if (typeof Swal !== 'undefined') Swal.fire('Atenção', data.message || 'Erro', 'warning');
              return;
            }
            if (typeof Swal !== 'undefined') {
              Swal.fire('Enviado', data.message, 'success').then(function () {
                window.location.reload();
              });
            } else {
              window.location.reload();
            }
          });
      });
    }

    document.querySelectorAll('.btn-cancel-solic').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-id');
        if (!id) return;
        var csrf = form ? form.querySelector('input[name="_csrf"]') : null;
        var fd = new FormData();
        fd.append('acao', 'cancelar');
        fd.append('id', id);
        if (csrf) fd.append('_csrf', csrf.value);
        fetch(window.location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.success) window.location.reload();
            else if (typeof Swal !== 'undefined') Swal.fire('Erro', data.message, 'error');
          });
      });
    });
  });
})();
