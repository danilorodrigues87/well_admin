/** CRUD Planos — itens de resíduo (saldo + excedente) */
(function ($) {
  'use strict';

  function apiBase() {
    return (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '');
  }

  function tipoOptionsHtml() {
    var tpl = document.getElementById('tpl-tipo-options');
    return tpl ? tpl.innerHTML.trim() : '<option value="">—</option>';
  }

  function addPlanoItemRow(data) {
    data = data || {};
    var idx = 'pi' + Date.now() + Math.random().toString(36).slice(2, 6);
    var html = '<tr data-row="' + idx + '">' +
      '<td><select class="form-select form-select-sm plano-item-tipo">' + tipoOptionsHtml() + '</select>' +
      '<input class="form-control form-control-sm mt-1 plano-item-nome" placeholder="Nome exibido" value="' + esc(data.nome || '') + '"/></td>' +
      '<td><input class="form-control form-control-sm plano-item-cod" value="' + esc(data.cod_ibama || '') + '"/></td>' +
      '<td><input class="form-control form-control-sm plano-item-saldo" inputmode="decimal" value="' + esc(data.saldo_incluso != null ? data.saldo_incluso : '') + '"/></td>' +
      '<td><select class="form-select form-select-sm plano-item-unidade">' +
      '<option value="kg">kg</option><option value="l">l</option><option value="un">un</option></select></td>' +
      '<td><input class="form-control form-control-sm plano-item-exced" inputmode="decimal" placeholder="0,00" value="' + esc(data.valor_excedente != null ? data.valor_excedente : '') + '"/></td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-plano-item" title="Remover"><i class="fas fa-times"></i></button></td>' +
      '</tr>';
    var $row = $(html);
    $('#plano-itens-body').append($row);
    if (data.tipo_residuo_id) {
      $row.find('.plano-item-tipo').val(String(data.tipo_residuo_id));
    }
    if (data.unidade) {
      $row.find('.plano-item-unidade').val(data.unidade);
    }
  }

  function esc(v) {
    return String(v).replace(/"/g, '&quot;').replace(/</g, '&lt;');
  }

  function collectItens() {
    var itens = [];
    $('#plano-itens-body tr').each(function () {
      var $r = $(this);
      var nome = ($r.find('.plano-item-nome').val() || '').trim();
      if (!nome) return;
      itens.push({
        tipo_residuo_id: parseInt($r.find('.plano-item-tipo').val(), 10) || 0,
        nome: nome,
        cod_ibama: ($r.find('.plano-item-cod').val() || '').trim(),
        saldo_incluso: parseFloat(String($r.find('.plano-item-saldo').val()).replace(',', '.')) || 0,
        unidade: $r.find('.plano-item-unidade').val() || 'kg',
        valor_excedente: parseFloat(String($r.find('.plano-item-exced').val()).replace(',', '.')) || 0
      });
    });
    return itens;
  }

  $(document).on('click', '#btn-add-plano-item', function () {
    addPlanoItemRow({});
  });

  $(document).on('click', '.btn-remove-plano-item', function () {
    $(this).closest('tr').remove();
  });

  $(document).on('change', '.plano-item-tipo', function () {
    var $opt = $(this).find('option:selected');
    var $row = $(this).closest('tr');
    if ($opt.val()) {
      $row.find('.plano-item-nome').val($opt.data('nome') || '');
      $row.find('.plano-item-cod').val($opt.data('cod') || '');
    }
  });

  window.novoRegistro = function () {
    $('#crud-id').val(0);
    $('#crud-nome, #crud-descricao, #crud-valor_mensal, #crud-coletas_mensais').val('');
    $('#crud-tipo').val('');
    $('#crud-ativo').val('1');
    $('#plano-itens-body').empty();
    addPlanoItemRow({});
    bootstrap.Modal.getOrCreateInstance(document.getElementById('crudModal')).show();
  };

  var origEditar = window.editar;
  window.editar = function (id) {
    $.ajax({
      url: apiBase() + (window.CRUD && window.CRUD.baseUrl ? window.CRUD.baseUrl : '/painel/planos'),
      method: 'POST',
      data: {
        acao: 'get',
        id: id,
        _csrf: document.querySelector('#crud-form input[name="_csrf"]')?.value || ''
      },
      dataType: 'json'
    }).done(function (resp) {
      var data = typeof resp === 'object' ? resp : JSON.parse(resp);
      if (!data.success) {
        if (typeof Swal !== 'undefined') Swal.fire('Erro', data.message || 'Erro', 'error');
        return;
      }
      $('#crud-id').val(data.id || 0);
      $('#crud-nome').val(data.nome || '');
      $('#crud-descricao').val(data.descricao || '');
      $('#crud-valor_mensal').val(data.valor_mensal != null ? String(data.valor_mensal).replace('.', ',') : '');
      $('#crud-coletas_mensais').val(data.coletas_mensais || '');
      $('#crud-tipo').val(data.tipo || '');
      $('#crud-ativo').val(String(data.ativo != null ? data.ativo : 1));
      $('#plano-itens-body').empty();
      (data.itens || []).forEach(function (item) { addPlanoItemRow(item); });
      if (!data.itens || !data.itens.length) addPlanoItemRow({});
      bootstrap.Modal.getOrCreateInstance(document.getElementById('crudModal')).show();
    }).fail(function () {
      if (typeof origEditar === 'function') origEditar(id);
    });
  };

  window.salvarPlano = function () {
    var csrf = document.querySelector('#crud-form input[name="_csrf"]')?.value || '';
    var payload = {
      acao: 'save',
      _csrf: csrf,
      id: $('#crud-id').val(),
      nome: $('#crud-nome').val(),
      descricao: $('#crud-descricao').val(),
      valor_mensal: $('#crud-valor_mensal').val(),
      coletas_mensais: $('#crud-coletas_mensais').val(),
      tipo: $('#crud-tipo').val(),
      ativo: $('#crud-ativo').val(),
      itens_json: JSON.stringify(collectItens())
    };
    $.ajax({
      url: apiBase() + (window.CRUD && window.CRUD.baseUrl ? window.CRUD.baseUrl : '/painel/planos'),
      method: 'POST',
      data: payload,
      dataType: 'json'
    }).done(function (resp) {
      var data = typeof resp === 'object' ? resp : JSON.parse(resp);
      if (data.success) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('crudModal')).hide();
        if (typeof Swal !== 'undefined') Swal.fire('Sucesso', data.message || 'Salvo.', 'success');
        if (typeof window.loadPage === 'function') window.loadPage(1);
      } else if (typeof Swal !== 'undefined') {
        Swal.fire('Erro', data.message || 'Erro ao salvar.', 'error');
      }
    });
  };
})(jQuery);
