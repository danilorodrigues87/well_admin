/** CRUD Planos — itens por tipo_residuo_id */
(function ($) {
  'use strict';

  var rowSeq = 0;

  function apiBase() {
    return (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '');
  }

  function tipoOptionsHtml() {
    var tpl = document.getElementById('tpl-tipo-options');
    return tpl ? tpl.innerHTML.trim() : '<option value="">—</option>';
  }

  function esc(v) {
    return String(v).replace(/"/g, '&quot;').replace(/</g, '&lt;');
  }

  function formatDecimal(v) {
    if (v == null || v === '') return '';
    return String(v).replace('.', ',');
  }

  function syncRowMode($row) {
    var credito = $row.find('.plano-item-credito').is(':checked');
    var $saldo = $row.find('.plano-item-saldo');
    var $pool = $row.find('.plano-item-compartilhado');
    var $exced = $row.find('.plano-item-exced');
    if (credito) {
      $saldo.val('').prop('disabled', true).attr('placeholder', '—');
      $pool.prop('checked', false).prop('disabled', true);
      $exced.attr('placeholder', '0,50');
    } else {
      $saldo.prop('disabled', false).attr('placeholder', '0');
      $pool.prop('disabled', false);
      $exced.attr('placeholder', '0,00');
    }
  }

  function addPlanoItemRow(data) {
    data = data || {};
    rowSeq += 1;
    var poolId = 'plano-pool-' + rowSeq;
    var creditoId = 'plano-credito-' + rowSeq;
    var saldo = data.saldo_incluso != null ? data.saldo_incluso : '';
    var html = '<tr>' +
      '<td><select class="form-select form-select-sm plano-item-tipo" required>' + tipoOptionsHtml() + '</select></td>' +
      '<td><input class="form-control form-control-sm plano-item-saldo" inputmode="decimal" placeholder="0" value="' + esc(saldo) + '"/></td>' +
      '<td><input class="form-control form-control-sm plano-item-exced" inputmode="decimal" placeholder="0,00" value="' + esc(formatDecimal(data.valor_excedente)) + '"/></td>' +
      '<td class="text-center align-middle">' +
        '<div class="form-check form-check-inline justify-content-center m-0">' +
          '<input type="checkbox" class="form-check-input plano-item-credito" id="' + creditoId + '"' +
            (data.gera_credito ? ' checked' : '') +
            ' title="Desconto na mensalidade: kg coletados × tarifa"/>' +
        '</div>' +
      '</td>' +
      '<td class="text-center align-middle">' +
        '<div class="form-check form-check-inline justify-content-center m-0">' +
          '<input type="checkbox" class="form-check-input plano-item-compartilhado" id="' + poolId + '"' +
            (data.saldo_compartilhado ? ' checked' : '') +
            ' title="Saldo compartilhado: soma o saldo incluso com outros resíduos de mesmo valor excedente"/>' +
        '</div>' +
      '</td>' +
      '<td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-plano-item" title="Remover"><i class="fas fa-times"></i></button></td>' +
      '</tr>';
    var $row = $(html);
    $('#plano-itens-body').append($row);
    if (data.tipo_residuo_id) {
      $row.find('.plano-item-tipo').val(String(data.tipo_residuo_id));
    }
    syncRowMode($row);
  }

  function collectItens() {
    var itens = [];
    var seen = {};
    $('#plano-itens-body tr').each(function () {
      var $r = $(this);
      var tipoId = parseInt($r.find('.plano-item-tipo').val(), 10) || 0;
      if (!tipoId) return;
      if (seen[tipoId]) {
        throw new Error('Resíduo duplicado no plano.');
      }
      seen[tipoId] = true;
      var geraCredito = $r.find('.plano-item-credito').is(':checked');
      itens.push({
        tipo_residuo_id: tipoId,
        saldo_incluso: geraCredito ? 0 : (parseFloat(String($r.find('.plano-item-saldo').val()).replace(',', '.')) || 0),
        unidade: 'kg',
        valor_excedente: parseFloat(String($r.find('.plano-item-exced').val()).replace(',', '.')) || 0,
        saldo_compartilhado: geraCredito ? 0 : ($r.find('.plano-item-compartilhado').is(':checked') ? 1 : 0),
        gera_credito: geraCredito ? 1 : 0
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

  $(document).on('change', '.plano-item-credito', function () {
    syncRowMode($(this).closest('tr'));
  });

  window.novoRegistro = function () {
    $('#crud-id').val(0);
    $('#crud-nome, #crud-descricao, #crud-valor_mensal, #crud-coletas_mensais, #crud-coletas_periodo_meses').val('');
    $('#crud-coletas_por_periodo').val('1');
    $('#crud-tipo').val('');
    $('#crud-ativo').val('1');
    $('#plano-itens-body').empty();
    rowSeq = 0;
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
      $('#crud-coletas_mensais').val(data.coletas_mensais != null ? String(data.coletas_mensais).replace('.', ',') : '');
      $('#crud-coletas_periodo_meses').val(data.coletas_periodo_meses != null && data.coletas_periodo_meses !== '' ? String(data.coletas_periodo_meses) : '');
      $('#crud-coletas_por_periodo').val(data.coletas_por_periodo != null ? String(data.coletas_por_periodo) : '1');
      $('#crud-tipo').val(data.tipo || '');
      $('#crud-ativo').val(String(data.ativo != null ? data.ativo : 1));
      $('#plano-itens-body').empty();
      rowSeq = 0;
      (data.itens || []).forEach(function (item) { addPlanoItemRow(item); });
      if (!data.itens || !data.itens.length) addPlanoItemRow({});
      bootstrap.Modal.getOrCreateInstance(document.getElementById('crudModal')).show();
    }).fail(function () {
      if (typeof origEditar === 'function') origEditar(id);
    });
  };

  window.salvarPlano = function () {
    var csrf = document.querySelector('#crud-form input[name="_csrf"]')?.value || '';
    var itens;
    try {
      itens = collectItens();
      if (!itens.length) {
        if (typeof Swal !== 'undefined') Swal.fire('Atenção', 'Adicione ao menos um resíduo.', 'warning');
        return;
      }
    } catch (e) {
      if (typeof Swal !== 'undefined') Swal.fire('Erro', e.message || 'Itens inválidos.', 'error');
      return;
    }
    var payload = {
      acao: 'salvar',
      _csrf: csrf,
      id: $('#crud-id').val(),
      nome: $('#crud-nome').val(),
      descricao: $('#crud-descricao').val(),
      valor_mensal: $('#crud-valor_mensal').val(),
      coletas_mensais: $('#crud-coletas_mensais').val(),
      coletas_periodo_meses: $('#crud-coletas_periodo_meses').val(),
      coletas_por_periodo: $('#crud-coletas_por_periodo').val(),
      tipo: $('#crud-tipo').val(),
      ativo: $('#crud-ativo').val(),
      itens_json: JSON.stringify(itens)
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
    }).fail(function (xhr) {
      var msg = 'Erro ao salvar plano.';
      if (xhr.responseJSON && xhr.responseJSON.message) {
        msg = xhr.responseJSON.message;
      }
      if (typeof Swal !== 'undefined') Swal.fire('Erro', msg, 'error');
    });
  };
})(jQuery);
