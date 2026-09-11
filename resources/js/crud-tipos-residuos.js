/** Cascata classe → grupos no CRUD de tipos de resíduos */
(function ($) {
  'use strict';

  function apiBase() {
    return (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '');
  }

  function loadGrupos(classeId, selectedId, targetSelector) {
    targetSelector = targetSelector || '#crud-grupo_id';
    if (!classeId) {
      $(targetSelector).html(targetSelector === '#filter-grupo_id'
        ? '<option value="">Grupo: todos</option>'
        : '<option value="">— Selecione a classe primeiro —</option>');
      return;
    }
    $.ajax({
      url: apiBase() + '/painel/tipos-residuos',
      method: 'POST',
      data: {
        acao: 'grupos_por_classe',
        classe_id: classeId,
        _csrf: document.querySelector('#crud-form input[name="_csrf"]')?.value || ''
      },
      dataType: 'json'
    }).done(function (resp) {
      var data = typeof resp === 'object' ? resp : JSON.parse(resp);
      if (data.success && data.options) {
        var html = data.options;
        if (targetSelector === '#filter-grupo_id') {
          html = html.replace('— Selecione —', 'Grupo: todos');
        }
        $(targetSelector).html(html);
        if (selectedId) {
          $(targetSelector).val(String(selectedId));
        }
      }
    });
  }

  $(document).on('change', '#crud-classe_id', function () {
    loadGrupos($(this).val(), 0, '#crud-grupo_id');
  });

  $(document).on('change', '#filter-classe_id', function () {
    $('#filter-grupo_id').val('');
    loadGrupos($(this).val(), 0, '#filter-grupo_id');
    if (typeof window.loadPage === 'function') {
      window.loadPage(1);
    }
  });

  var origEditar = window.editar;
  window.editar = function (id) {
    $.ajax({
      url: (window.CRUD && window.CRUD.baseUrl ? apiBase() + window.CRUD.baseUrl : apiBase() + '/painel/tipos-residuos'),
      method: 'POST',
      data: { acao: 'get', id: id, _csrf: document.querySelector('#crud-form input[name="_csrf"]')?.value || '' },
      dataType: 'json'
    }).done(function (resp) {
      var data = typeof resp === 'object' ? resp : JSON.parse(resp);
      if (!data.success) {
        if (typeof Swal !== 'undefined') {
          Swal.fire('Erro', data.message || 'Erro ao carregar.', 'error');
        } else {
          alert(data.message || 'Erro ao carregar.');
        }
        return;
      }
      $('#crud-id').val(data.id || 0);
      $('#crud-nome').val(data.nome || '');
      $('#crud-cod_ibama').val(data.cod_ibama || '');
      $('#crud-tra_codigo').val(data.tra_codigo || '');
      $('#crud-tie_codigo').val(data.tie_codigo || '');
      $('#crud-tia_codigo').val(data.tia_codigo || '');
      $('#crud-cla_codigo').val(data.cla_codigo || '');
      $('#crud-uni_codigo').val(data.uni_codigo || '');
      $('#crud-classe_id').val(data.classe_id || '');
      if (data.grupos_options) {
        $('#crud-grupo_id').html(data.grupos_options);
      } else {
        loadGrupos(data.classe_id, data.grupo_id);
      }
      bootstrap.Modal.getOrCreateInstance(document.getElementById('crudModal')).show();
    }).fail(function () {
      if (typeof origEditar === 'function') {
        origEditar(id);
      }
    });
  };
})(jQuery);
