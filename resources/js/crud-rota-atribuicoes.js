/** Atribuições rota × cliente (sem coletor fixo na rota) */
(function ($) {
  'use strict';

  var _buscaTimer = null;
  var clienteTomSelect = null;

  function apiUrl() {
    var cfg = window.ROTA_ATRIB || {};
    var base = (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '');
    return base + (cfg.baseUrl || '/painel/rotas/atribuicoes/0');
  }

  function getCsrf() {
    return document.querySelector('#add-cliente-form input[name="_csrf"]')?.value
      || document.querySelector('input[name="_csrf"]')?.value || '';
  }

  function getFilterData() {
    var data = {};
    $('#barra-filtros-lista, .crud-filters-row').first()
      .find('input[name], select[name]').each(function () {
        var name = $(this).attr('name');
        if (name) {
          data[name] = $(this).val() || '';
        }
      });
    return data;
  }

  function updateStats(stats) {
    if (!stats) return;
    $('#stat-total').text(stats.total || 0);
  }

  function swalErr(msg) {
    if (typeof Swal !== 'undefined') {
      Swal.fire('Erro', msg, 'error');
    } else {
      alert(msg);
    }
  }

  function initTomSelectCliente() {
    var el = document.getElementById('add-cliente_id');
    if (!el) {
      return;
    }
    if (typeof TomSelect === 'undefined') {
      swalErr('Biblioteca de busca não carregou. Recarregue a página (Ctrl+F5).');
      return;
    }
    if (clienteTomSelect) {
      return;
    }
    try {
      clienteTomSelect = new TomSelect(el, {
        create: false,
        maxOptions: 5000,
        placeholder: 'Digite para buscar cliente…',
        allowEmptyOption: true,
        sortField: { field: 'text', direction: 'asc' },
        plugins: ['dropdown_input'],
        render: {
          no_results: function () {
            return '<div class="no-results px-2 py-1">Nenhum cliente encontrado</div>';
          }
        }
      });
    } catch (err) {
      clienteTomSelect = null;
      swalErr('Erro ao iniciar busca de clientes: ' + (err.message || 'erro desconhecido'));
    }
  }

  function rebuildTomSelectCliente(optionsHtml) {
    var el = document.getElementById('add-cliente_id');
    if (!el) {
      return;
    }
    if (clienteTomSelect) {
      clienteTomSelect.destroy();
      clienteTomSelect = null;
    }
    el.innerHTML = '<option value="">— Selecione —</option>' + (optionsHtml || '');
    initTomSelectCliente();
  }

  function refreshClientesSelect(callback) {
    $.post(apiUrl(), {
      acao: 'clientes_disponiveis',
      _csrf: getCsrf()
    }, function (resp) {
      resp = typeof resp === 'object' ? resp : JSON.parse(resp);
      if (!resp.success) {
        swalErr(resp.message || 'Erro ao atualizar lista de clientes.');
        return;
      }
      rebuildTomSelectCliente(resp.options_html || '');
      if (typeof callback === 'function') {
        callback();
      }
    }, 'json').fail(function () {
      swalErr('Erro ao atualizar lista de clientes.');
    });
  }

  window.loadPage = function (page) {
    page = page || 1;
    $.ajax({
      url: apiUrl(),
      method: 'POST',
      data: $.extend({
        acao: 'listar_atribuicoes',
        page: page,
        _csrf: getCsrf()
      }, getFilterData()),
      dataType: 'json'
    }).done(function (data) {
      if (typeof data === 'string') data = JSON.parse(data);
      if (data.itens !== undefined) {
        $('#crud-tbody').html(data.itens);
        $('#crud-pagination').html(data.pagination || '');
      }
      updateStats(data.stats);
    }).fail(function (xhr) {
      swalErr('Erro ao carregar lista (' + xhr.status + ').');
    });
  };

  window.removerAtrib = function (id) {
    var exec = function () {
      $.post(apiUrl(), { acao: 'excluir_atribuicao', id: id, _csrf: getCsrf() }, function (resp) {
        resp = typeof resp === 'object' ? resp : JSON.parse(resp);
        if (!resp.success) {
          swalErr(resp.message || 'Erro');
          return;
        }
        if (typeof Swal !== 'undefined') {
          Swal.fire({ title: 'Removido!', text: resp.message || 'Cliente removido da rota.', icon: 'success', timer: 2000, showConfirmButton: false });
        }
        refreshClientesSelect();
        loadPage(1);
      }, 'json');
    };

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Remover da rota?',
        text: 'O cliente deixa de pertencer a esta rota (não desativa o cadastro).',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sim, remover',
        cancelButtonText: 'Cancelar'
      }).then(function (r) {
        if (r.isConfirmed) exec();
      });
    } else if (confirm('Remover este cliente da rota?')) {
      exec();
    }
  };

  window.adicionarCliente = function () {
    var clienteId = clienteTomSelect ? clienteTomSelect.getValue() : ($('#add-cliente_id').val() || '');
    if (!clienteId) {
      swalErr('Selecione um cliente.');
      return;
    }
    $.post(apiUrl(), {
      acao: 'salvar_atribuicao',
      cliente_id: clienteId,
      _csrf: getCsrf()
    }, function (resp) {
      resp = typeof resp === 'object' ? resp : JSON.parse(resp);
      if (!resp.success) {
        swalErr(resp.message || 'Erro');
        return;
      }
      if (clienteTomSelect) {
        clienteTomSelect.removeOption(clienteId);
        clienteTomSelect.clear(true);
      }
      var modalEl = document.getElementById('addClienteModal');
      if (modalEl) {
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      }
      loadPage(1);
    }, 'json');
  };

  $(function () {
    initTomSelectCliente();
    loadPage(1);

    $(document).on('input', '#barra-filtros-lista input[name="busca"]', function () {
      clearTimeout(_buscaTimer);
      _buscaTimer = setTimeout(function () { loadPage(1); }, 350);
    });

    document.getElementById('addClienteModal')?.addEventListener('shown.bs.modal', function () {
      if (clienteTomSelect) {
        clienteTomSelect.clear(true);
        clienteTomSelect.close();
      }
    });

    document.getElementById('addClienteModal')?.addEventListener('hidden.bs.modal', function () {
      if (clienteTomSelect) {
        clienteTomSelect.clear(true);
      }
    });
  });
})(jQuery);
