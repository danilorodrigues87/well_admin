(function ($) {
  'use strict';

  function postUrl() {
    return typeof wellAppUrl === 'function'
      ? wellAppUrl(null, window.CRUD && window.CRUD.baseUrl ? window.CRUD.baseUrl : '/painel/coletas')
      : (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '') + '/painel/coletas';
  }

  function csrf() {
    var el = document.querySelector('#crud-form input[name="_csrf"]')
      || document.querySelector('input[name="_csrf"]');
    return el ? el.value : '';
  }

  function parseResp(d) {
    return typeof d === 'object' ? d : JSON.parse(d);
  }

  function afterOk(id, msg, icon) {
    if (typeof Swal !== 'undefined') {
      Swal.fire('SINIR', msg || 'Concluído.', icon || 'success');
    } else {
      alert(msg || 'Concluído.');
    }
    if (typeof window.detalhar === 'function') {
      window.detalhar(id);
    }
    if (typeof listar === 'function') {
      listar(null, 1);
    }
  }

  function afterErr(msg) {
    if (typeof Swal !== 'undefined') {
      Swal.fire('Erro SINIR', msg || 'Erro', 'error');
    } else {
      alert(msg || 'Erro');
    }
  }

  window.sinirReenviar = function (id) {
    var doPost = function () {
      $.post(postUrl(), { acao: 'sinir_reenviar', id: id, _csrf: csrf() }, function (d) {
        d = parseResp(d);
        if (d.success) {
          afterOk(id, d.message);
        } else {
          afterErr(d.message);
        }
      }, 'json').fail(function () {
        afterErr('Falha de rede.');
      });
    };
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Registrar no SINIR?',
        text: 'Será enviado (ou reenviado) o manifesto nacional para esta coleta.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Continuar'
      }).then(function (r) {
        if (r.isConfirmed) {
          doPost();
        }
      });
    } else if (confirm('Registrar no SINIR?')) {
      doPost();
    }
  };

  window.sinirConsultar = function (id) {
    $.post(postUrl(), { acao: 'sinir_consultar', id: id, _csrf: csrf() }, function (d) {
      d = parseResp(d);
      if (d.success) {
        afterOk(id, d.message, 'info');
      } else {
        afterErr(d.message);
      }
    }, 'json').fail(function () {
      afterErr('Falha de rede.');
    });
  };

  window.sinirCancelar = function (id) {
    var run = function (justificativa) {
      $.post(postUrl(), {
        acao: 'sinir_cancelar',
        id: id,
        justificativa: justificativa,
        _csrf: csrf()
      }, function (d) {
        d = parseResp(d);
        if (d.success) {
          afterOk(id, d.message);
        } else {
          afterErr(d.message);
        }
      }, 'json').fail(function () {
        afterErr('Falha de rede.');
      });
    };

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Cancelar MTR no SINIR',
        input: 'textarea',
        inputLabel: 'Justificativa (obrigatória)',
        inputPlaceholder: 'Motivo do cancelamento conforme exigência do órgão…',
        inputAttributes: { maxlength: 500 },
        showCancelButton: true,
        confirmButtonText: 'Cancelar manifesto',
        confirmButtonColor: '#dc3545',
        preConfirm: function (value) {
          if (!value || !String(value).trim()) {
            Swal.showValidationMessage('Informe a justificativa.');
          }
          return value;
        }
      }).then(function (r) {
        if (r.isConfirmed && r.value) {
          run(String(r.value).trim());
        }
      });
    } else {
      var j = prompt('Justificativa do cancelamento:');
      if (j && j.trim()) {
        run(j.trim());
      }
    }
  };

  window.detalhar = function (id) {
    $.post(postUrl(), { acao: 'get', id: id, _csrf: csrf() }, function (d) {
      d = parseResp(d);
      if (d.success && d.html) {
        $('#detalhe-body').html(d.html);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('detalheModal')).show();
      } else {
        afterErr(d.message || 'Erro ao carregar detalhe.');
      }
    }, 'json').fail(function () {
      afterErr('Falha ao carregar detalhe.');
    });
  };
})(jQuery);
