(function () {
  'use strict';

  var tipoResiduoTomSelect = null;

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

  window.finalizarColeta = function () {
    var executar = function () {
      swalLoading(
        'Finalizando coleta…',
        'Gerando número MTR e salvando evidências.<br><small class="text-muted">Não feche esta página.</small>'
      );

      var fd = new FormData(document.getElementById('form-finalizar'));
      fd.append('acao', 'finalizar');
      fd.append('_csrf', csrf());

      $.ajax({
        url: baseUrl(),
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        timeout: 120000
      }).done(function (r) {
        r = parseJsonResp(r);
        if (typeof Swal !== 'undefined') Swal.close();
        if (r.success) {
          swalOk(r.message || 'Coleta finalizada!', function () {
            window.location = r.redirect || ((typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '') + '/painel/coletas');
          });
        } else {
          swalErr(r.message || 'Não foi possível finalizar.');
        }
      }).fail(function (xhr) {
        if (typeof Swal !== 'undefined') Swal.close();
        var msg = 'Erro ao finalizar a coleta.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        } else if (xhr.status === 0 || xhr.statusText === 'timeout') {
          msg = 'A operação demorou demais. Verifique em Coletas se o MTR foi gerado antes de tentar de novo.';
        } else if (xhr.responseText && xhr.responseText.indexOf('{') === -1) {
          msg = 'Erro no servidor. Verifique os logs ou tente novamente.';
        }
        swalErr(msg);
      });
    };

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Finalizar coleta?',
        text: 'Será gerado o número MTR e a coleta não poderá mais ser editada.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, finalizar',
        cancelButtonText: 'Cancelar'
      }).then(function (r) {
        if (r.isConfirmed) executar();
      });
    } else if (confirm('Finalizar coleta e gerar número MTR?')) {
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
