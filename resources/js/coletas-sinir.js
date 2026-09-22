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

  function checklistHtml(checks) {
    var html = '<ul class="list-unstyled mb-0 text-start">';
    (checks || []).forEach(function (c) {
      var icon = c.ok
        ? '<i class="fas fa-check-circle text-success me-2"></i>'
        : '<i class="fas fa-times-circle text-danger me-2"></i>';
      html += '<li class="mb-2">' + icon + '<strong>' + (c.label || '') + '</strong><br><small class="text-muted ms-4">'
        + (c.detail || '') + '</small></li>';
    });
    html += '</ul>';
    return html;
  }

  function doEnviarSinir(id) {
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
  }

  window.sinirReenviar = function (id) {
    $.post(postUrl(), { acao: 'sinir_precheck', id: id, _csrf: csrf() }, function (d) {
      d = parseResp(d);
      if (!d.success) {
        afterErr(d.message || 'Não foi possível validar.');
        return;
      }

      var checks = d.checks || [];
      var canSend = !!d.ok;
      var html = checklistHtml(checks);
      if (!canSend && d.errors && d.errors.length) {
        html += '<p class="small text-danger mt-2 mb-0">' + d.errors.join(' ') + '</p>';
      }

      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Gerar MTR no SINIR',
          html: html,
          icon: canSend ? 'question' : 'warning',
          showCancelButton: true,
          confirmButtonText: canSend ? 'Confirmar e emitir' : 'Fechar',
          showConfirmButton: canSend,
          cancelButtonText: 'Cancelar'
        }).then(function (r) {
          if (canSend && r.isConfirmed) {
            Swal.fire({
              title: 'Enviando…',
              allowOutsideClick: false,
              didOpen: function () { Swal.showLoading(); }
            });
            doEnviarSinir(id);
          }
        });
      } else if (canSend && confirm('Gerar MTR no SINIR?')) {
        doEnviarSinir(id);
      } else if (!canSend) {
        alert(d.errors ? d.errors.join('\n') : 'Corrija os itens pendentes.');
      }
    }, 'json').fail(function () {
      afterErr('Falha ao validar pré-requisitos.');
    });
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

  window.sinirReceber = function (id) {
    var run = function (resp, cargo) {
      $.post(postUrl(), {
        acao: 'sinir_receber',
        id: id,
        responsavel: resp || '',
        cargo: cargo || '',
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
        title: 'Receber MTR no SINIR (destinador)',
        html: '<p class="small text-muted text-start mb-2">Confirma o recebimento no portal nacional usando os dados da coleta (data de recebimento, itens e responsável).</p>',
        input: 'text',
        inputLabel: 'Responsável no destinador (opcional)',
        inputPlaceholder: 'Nome do balanceiro / responsável',
        showCancelButton: true,
        confirmButtonText: 'Registrar recebimento',
        preConfirm: function (value) {
          return { responsavel: value ? String(value).trim() : '' };
        }
      }).then(function (r) {
        if (r.isConfirmed) {
          Swal.fire({ title: 'Enviando…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
          run(r.value && r.value.responsavel ? r.value.responsavel : '', '');
        }
      });
    } else if (confirm('Registrar recebimento no SINIR?')) {
      run('', '');
    }
  };

  window.sinirEmitirCdf = function (id) {
    var run = function (resp) {
      $.post(postUrl(), {
        acao: 'sinir_emitir_cdf',
        id: id,
        responsavel: resp || '',
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
        title: 'Emitir CDF no SINIR',
        text: 'Emite o certificado de destinação para este MTR (destinador). Requer recebimento já registrado.',
        input: 'text',
        inputLabel: 'Responsável técnico (opcional)',
        showCancelButton: true,
        confirmButtonText: 'Emitir CDF'
      }).then(function (r) {
        if (r.isConfirmed) {
          Swal.fire({ title: 'Processando…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
          run(r.value ? String(r.value).trim() : '');
        }
      });
    } else if (confirm('Emitir CDF no SINIR?')) {
      run('');
    }
  };

  window.sinirBaixarCdf = function (id) {
    $.post(postUrl(), { acao: 'sinir_baixar_cdf', id: id, _csrf: csrf() }, function (d) {
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

  window.sinirBaixarPdf = function (id) {
    $.post(postUrl(), { acao: 'sinir_baixar_pdf', id: id, _csrf: csrf() }, function (d) {
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

  window.sinirCdfUpload = function (ev, id) {
    ev.preventDefault();
    var form = ev.target;
    var input = form.querySelector('input[type="file"]');
    if (!input || !input.files || !input.files[0]) {
      afterErr('Selecione um PDF.');
      return false;
    }
    var fd = new FormData();
    fd.append('acao', 'sinir_cdf_upload');
    fd.append('id', String(id));
    fd.append('_csrf', csrf());
    fd.append('cdf_file', input.files[0]);
    $.ajax({
      url: postUrl(),
      type: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json'
    }).done(function (d) {
      d = parseResp(d);
      if (d.success) {
        afterOk(id, d.message);
      } else {
        afterErr(d.message);
      }
    }).fail(function () {
      afterErr('Falha ao enviar PDF.');
    });
    return false;
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
