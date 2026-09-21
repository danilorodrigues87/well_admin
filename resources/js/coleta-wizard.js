(function () {
  'use strict';

  var tipoResiduoTomSelect = null;
  var rascunhoConferido = !!(window.COLETA_WIZARD && window.COLETA_WIZARD.rascunhoConferido);

  function baseUrl() {
    var path = (window.CRUD && window.CRUD.baseUrl) ? window.CRUD.baseUrl.replace(/^\/+/, '') : 'painel/coleta/nova';
    return (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '') + '/' + path;
  }

  function csrf() {
    return document.querySelector('#crud-form input[name="_csrf"]')?.value || '';
  }

  function parseJsonResp(resp) {
    if (resp === null || resp === undefined) {
      return { success: false, message: 'Resposta vazia do servidor.' };
    }
    if (typeof resp === 'object') {
      return resp;
    }
    try {
      return JSON.parse(resp);
    } catch (e) {
      return { success: false, message: 'Resposta inválida do servidor.' };
    }
  }

  function swalErr(msg) {
    if (typeof Swal !== 'undefined') {
      Swal.fire('Erro', msg || 'Erro', 'error');
    } else {
      alert(msg || 'Erro');
    }
  }

  function swalOk(msg, then) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({ title: 'Pronto!', text: msg, icon: 'success' }).then(function () {
        if (then) then();
      });
    } else {
      alert(msg);
      if (then) then();
    }
  }

  function swalLoading(title, html) {
    if (typeof Swal === 'undefined') {
      return;
    }
    Swal.fire({
      title: title || 'Aguarde…',
      html: html || 'Processando…',
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: function () {
        Swal.showLoading();
      }
    });
  }

  function setRascunhoConferido(resumoHtml, evidenciasHtml) {
    rascunhoConferido = true;
    if (window.COLETA_WIZARD) {
      window.COLETA_WIZARD.rascunhoConferido = true;
    }
    $('#rascunho-resumo-wrap').removeClass('d-none');
    if (resumoHtml) {
      $('#resumo-rascunho').html(resumoHtml);
    }
    if (evidenciasHtml) {
      $('#evidencias-preview').html(evidenciasHtml);
    }
    $('#btn-gerar-mtr').prop('disabled', false);
  }

  function tipoResiduoValor() {
    if (tipoResiduoTomSelect) {
      return tipoResiduoTomSelect.getValue() || '';
    }
    return $('#tipo_residuo_id').val() || '';
  }

  function initTomSelectResiduos() {
    var el = document.getElementById('tipo_residuo_id');
    if (!el || typeof TomSelect === 'undefined') {
      return;
    }
    if (tipoResiduoTomSelect) {
      tipoResiduoTomSelect.destroy();
      tipoResiduoTomSelect = null;
    }
    tipoResiduoTomSelect = new TomSelect(el, {
      create: false,
      maxOptions: 500,
      placeholder: 'Digite para buscar resíduo…',
      allowEmptyOption: true,
      sortField: { field: 'text', direction: 'asc' },
      plugins: ['dropdown_input'],
      render: {
        no_results: function () {
          return '<div class="no-results px-2 py-1">Nenhum resíduo encontrado</div>';
        }
      }
    });
  }

  window.salvarTransporte = function () {
    swalLoading('Salvando transporte…', 'Aguarde um instante.');
    var data = $('#form-transporte').serializeArray();
    data.push({ name: 'acao', value: 'salvar_transporte' });
    data.push({ name: '_csrf', value: csrf() });
    $.post(baseUrl(), data, function (r) {
      r = parseJsonResp(r);
      if (typeof Swal !== 'undefined') Swal.close();
      if (r.success) {
        bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#tab-residuos"]')).show();
      } else {
        swalErr(r.message);
      }
    }, 'json').fail(function () {
      if (typeof Swal !== 'undefined') Swal.close();
      swalErr('Erro ao salvar transporte.');
    });
  };

  window.adicionarItem = function () {
    var tipoId = tipoResiduoValor();
    if (!tipoId) {
      swalErr('Selecione um tipo de resíduo.');
      return;
    }
    swalLoading('Adicionando resíduo…');
    $.post(baseUrl(), {
      acao: 'adicionar_item',
      tipo_residuo_id: tipoId,
      quantidade: $('#quantidade').val(),
      unidade: $('#unidade').val(),
      _csrf: csrf()
    }, function (r) {
      r = parseJsonResp(r);
      if (typeof Swal !== 'undefined') Swal.close();
      if (r.success && r.itens_html) {
        $('#itens-tbody').html(r.itens_html);
        $('#quantidade').val('1');
        if (tipoResiduoTomSelect) {
          tipoResiduoTomSelect.clear(true);
        }
      } else {
        swalErr(r.message);
      }
    }, 'json').fail(function () {
      if (typeof Swal !== 'undefined') Swal.close();
      swalErr('Erro ao adicionar resíduo.');
    });
  };

  window.removerItem = function (id) {
    $.post(baseUrl(), { acao: 'remover_item', item_id: id, _csrf: csrf() }, function (r) {
      r = parseJsonResp(r);
      if (r.success && r.itens_html) {
        $('#itens-tbody').html(r.itens_html);
      }
    }, 'json');
  };

  window.salvarRascunhoFinal = function () {
    swalLoading(
      'Salvando rascunho…',
      'Enviando fotos e relatório.<br><small class="text-muted">Aguarde — pode demorar com fotos grandes.</small>'
    );

    var fd = new FormData(document.getElementById('form-finalizar'));
    fd.append('acao', 'salvar_rascunho_final');
    fd.append('_csrf', csrf());

    $.ajax({
      url: baseUrl(),
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json',
      timeout: 180000
    }).done(function (r) {
      r = parseJsonResp(r);
      if (typeof Swal !== 'undefined') Swal.close();
      if (r.success) {
        setRascunhoConferido(r.resumo_html, r.evidencias_html);
        swalOk(r.message || 'Rascunho salvo!');
        document.getElementById('form-finalizar').querySelectorAll('input[type="file"]').forEach(function (inp) {
          inp.value = '';
        });
      } else {
        swalErr(r.message || 'Não foi possível salvar o rascunho.');
      }
    }).fail(function (xhr) {
      if (typeof Swal !== 'undefined') Swal.close();
      var msg = 'Erro ao salvar rascunho.';
      if (xhr.responseJSON && xhr.responseJSON.message) {
        msg = xhr.responseJSON.message;
      } else if (xhr.status === 0 || xhr.statusText === 'timeout') {
        msg = 'Upload demorou demais. Tente fotos menores ou apenas o relatório.';
      }
      swalErr(msg);
    });
  };

  window.gerarMtr = function () {
    if (!rascunhoConferido) {
      swalErr('Salve o rascunho antes de finalizar a coleta.');
      return;
    }

    var dataReceb = document.querySelector('#form-transporte input[name="data_recebimento"]')?.value || '';
    if (!dataReceb) {
      swalErr('Informe a data de recebimento na aba Transporte e clique em "Salvar e continuar" antes de finalizar.');
      bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#tab-transporte"]')).show();
      return;
    }

    var executar = function () {
      swalLoading('Finalizando…', 'Registrando coleta e enviando ao SINIR quando habilitado.');

      $.ajax({
        url: baseUrl(),
        method: 'POST',
        data: {
          acao: 'finalizar',
          _csrf: csrf()
        },
        dataType: 'json',
        timeout: 30000
      }).done(function (r) {
        r = parseJsonResp(r);
        if (typeof Swal !== 'undefined') Swal.close();
        if (r.success) {
          swalOk(r.message || 'Coleta finalizada!', function () {
            window.location = r.redirect || ((typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '') + '/painel/coletas');
          });
        } else {
          swalErr(r.message || 'Não foi possível finalizar a coleta.');
        }
      }).fail(function (xhr) {
        if (typeof Swal !== 'undefined') Swal.close();
        var msg = 'Erro ao finalizar coleta.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        } else if (xhr.status === 0 || xhr.statusText === 'timeout') {
          msg = 'Tempo esgotado. Verifique em Coletas se a coleta foi finalizada antes de tentar de novo.';
        }
        swalErr(msg);
      });
    };

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Finalizar coleta?',
        text: 'Os dados serão fechados. O número MTR aparecerá após registro no SINIR.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, finalizar',
        cancelButtonText: 'Voltar'
      }).then(function (r) {
        if (r.isConfirmed) executar();
      });
    } else if (confirm('Finalizar coleta?')) {
      executar();
    }
  };

  window.cancelarColeta = function () {
    var executar = function () {
      swalLoading('Cancelando rascunho…');
      $.post(baseUrl(), { acao: 'cancelar', _csrf: csrf() }, function (r) {
        r = parseJsonResp(r);
        if (typeof Swal !== 'undefined') Swal.close();
        if (r.redirect) window.location = r.redirect;
      }, 'json').fail(function () {
        if (typeof Swal !== 'undefined') Swal.close();
        swalErr('Erro ao cancelar.');
      });
    };

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Cancelar rascunho?',
        text: 'Os dados desta coleta em andamento serão descartados.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, cancelar',
        cancelButtonText: 'Voltar'
      }).then(function (r) {
        if (r.isConfirmed) executar();
      });
    } else if (confirm('Cancelar este rascunho?')) {
      executar();
    }
  };

  $(function () {
    initTomSelectResiduos();
    document.querySelector('[data-bs-target="#tab-residuos"]')?.addEventListener('shown.bs.tab', function () {
      if (tipoResiduoTomSelect) {
        tipoResiduoTomSelect.focus();
      }
    });
  });
})();
