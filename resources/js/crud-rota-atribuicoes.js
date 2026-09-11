/** Atribuições rota × cliente × coletor */
(function ($) {
  'use strict';

  var _buscaTimer = null;

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
    $('#stat-sem-coletor').text(stats.sem_coletor || 0);
  }

  function swalErr(msg) {
    if (typeof Swal !== 'undefined') {
      Swal.fire('Erro', msg, 'error');
    } else {
      alert(msg);
    }
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

  window.aplicarColetorLote = function () {
    var coletorId = $('#bulk-coletor').val();
    if (!coletorId) {
      swalErr('Selecione um coletor.');
      return;
    }
    var exec = function () {
      $.post(apiUrl(), {
        acao: 'bulk_coletor',
        coletor_id: coletorId,
        only_sem_coletor: '1',
        _csrf: getCsrf()
      }, function (resp) {
        resp = typeof resp === 'object' ? resp : JSON.parse(resp);
        if (!resp.success) {
          swalErr(resp.message || 'Erro');
          return;
        }
        if (typeof Swal !== 'undefined') {
          Swal.fire({ title: 'Pronto!', text: resp.message, icon: 'success' });
        }
        loadPage(1);
      }, 'json');
    };

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Atribuir coletor em lote?',
        text: 'Todos os clientes sem coletor nesta rota receberão o coletor selecionado.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar'
      }).then(function (r) {
        if (r.isConfirmed) exec();
      });
    } else if (confirm('Atribuir coletor em lote?')) {
      exec();
    }
  };

  window.adicionarCliente = function () {
    var clienteId = $('#add-cliente_id').val();
    if (!clienteId) {
      swalErr('Selecione um cliente.');
      return;
    }
    $.post(apiUrl(), {
      acao: 'salvar_atribuicao',
      cliente_id: clienteId,
      coletor_id: $('#add-coletor_id').val() || '',
      _csrf: getCsrf()
    }, function (resp) {
      resp = typeof resp === 'object' ? resp : JSON.parse(resp);
      if (!resp.success) {
        swalErr(resp.message || 'Erro');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('addClienteModal')).hide();
      loadPage(1);
    }, 'json');
  };

  function buscarClientesDisponiveis() {
    $.post(apiUrl(), {
      acao: 'clientes_disponiveis',
      busca: $('input[name="busca"]', '#addClienteModal').val() || $('#add-busca').val() || '',
      _csrf: getCsrf()
    }, function (resp) {
      resp = typeof resp === 'object' ? resp : JSON.parse(resp);
      if (resp.success && resp.options) {
        $('#add-cliente_id').html(resp.options);
      }
    }, 'json');
  }

  $(function () {
    loadPage(1);

    $(document).on('change', '#barra-filtros-lista select[name]', function () {
      loadPage(1);
    });

    $(document).on('input', '#barra-filtros-lista input[name="busca"]', function () {
      clearTimeout(_buscaTimer);
      _buscaTimer = setTimeout(function () { loadPage(1); }, 350);
    });

    $(document).on('change', '.coletor-select', function () {
      var $sel = $(this);
      $.post(apiUrl(), {
        acao: 'update_atribuicao_coletor',
        id: $sel.data('id'),
        coletor_id: $sel.val() || '',
        _csrf: getCsrf()
      }, function (resp) {
        resp = typeof resp === 'object' ? resp : JSON.parse(resp);
        if (!resp.success) {
          swalErr(resp.message || 'Erro ao salvar coletor.');
        }
        loadPage(1);
      }, 'json');
    });

    $('#add-busca').on('keyup', function (e) {
      if (e.key === 'Enter') buscarClientesDisponiveis();
    });
    $('#addClienteModal').on('show.bs.modal', function () {
      $('#add-busca').val('');
      $('#add-cliente_id').html('<option value="">— Selecione —</option>');
      buscarClientesDisponiveis();
    });
  });
})(jQuery);
