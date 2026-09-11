(function ($) {
  'use strict';

  var _buscaTimer = null;

  function apiUrl() {
    var base = (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '');
    return base + '/painel/coleta/nova';
  }

  function csrf() {
    return document.querySelector('#crud-form input[name="_csrf"]')?.value || '';
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

  function loadPage(page) {
    page = page || 1;
    $.post(apiUrl(), $.extend({
      acao: 'listar_clientes',
      page: page,
      _csrf: csrf()
    }, getFilterData()), function (data) {
      data = typeof data === 'object' ? data : JSON.parse(data);
      if (data.itens !== undefined) {
        $('#crud-tbody').html(data.itens);
        $('#crud-pagination').html(data.pagination || '');
      }
    }, 'json').fail(function (xhr) {
      if (typeof Swal !== 'undefined') {
        Swal.fire('Erro', 'Erro ao carregar clientes (' + xhr.status + ').', 'error');
      } else {
        alert('Erro ao carregar clientes.');
      }
    });
  }

  window.loadPage = loadPage;

  window.iniciarColeta = function (clienteId) {
    $.post(apiUrl(), {
      acao: 'iniciar', cliente_id: clienteId, _csrf: csrf()
    }, function (r) {
      r = typeof r === 'object' ? r : JSON.parse(r);
      if (r.success && r.redirect) {
        window.location = r.redirect;
      } else if (typeof Swal !== 'undefined') {
        Swal.fire('Erro', r.message || 'Erro ao iniciar coleta.', 'error');
      } else {
        alert(r.message || 'Erro ao iniciar coleta.');
      }
    }, 'json');
  };

  $(function () {
    loadPage(1);

    $(document).on('change', '#barra-filtros-lista select[name], .crud-filters-row select[name]', function () {
      loadPage(1);
    });

    $(document).on('input', '#barra-filtros-lista input[name="busca"], .crud-filters-row input[name="busca"]', function () {
      clearTimeout(_buscaTimer);
      _buscaTimer = setTimeout(function () { loadPage(1); }, 350);
    });
  });
})(jQuery);
