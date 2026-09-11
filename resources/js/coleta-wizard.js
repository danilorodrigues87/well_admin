(function () {
  'use strict';

  function baseUrl() {
    var path = (window.CRUD && window.CRUD.baseUrl) ? window.CRUD.baseUrl.replace(/^\/+/, '') : 'painel/coleta/nova';
    return (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '') + '/' + path;
  }

  function csrf() {
    return document.querySelector('#crud-form input[name="_csrf"]')?.value || '';
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

  window.salvarTransporte = function () {
    var data = $('#form-transporte').serializeArray();
    data.push({ name: 'acao', value: 'salvar_transporte' });
    data.push({ name: '_csrf', value: csrf() });
    $.post(baseUrl(), data, function (r) {
      r = typeof r === 'object' ? r : JSON.parse(r);
      if (r.success) {
        bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#tab-residuos"]')).show();
      } else {
        swalErr(r.message);
      }
    }, 'json');
  };

  window.adicionarItem = function () {
    $.post(baseUrl(), {
      acao: 'adicionar_item',
      tipo_residuo_id: $('#tipo_residuo_id').val(),
      quantidade: $('#quantidade').val(),
      unidade: $('#unidade').val(),
      _csrf: csrf()
    }, function (r) {
      r = typeof r === 'object' ? r : JSON.parse(r);
      if (r.success && r.itens_html) {
        $('#itens-tbody').html(r.itens_html);
        $('#quantidade').val('1');
      } else {
        swalErr(r.message);
      }
    }, 'json');
  };

  window.removerItem = function (id) {
    $.post(baseUrl(), { acao: 'remover_item', item_id: id, _csrf: csrf() }, function (r) {
      r = typeof r === 'object' ? r : JSON.parse(r);
      if (r.success && r.itens_html) {
        $('#itens-tbody').html(r.itens_html);
      }
    }, 'json');
  };

  window.finalizarColeta = function () {
    var executar = function () {
      var fd = new FormData(document.getElementById('form-finalizar'));
      fd.append('acao', 'finalizar');
      fd.append('_csrf', csrf());
      $.ajax({
        url: baseUrl(), method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json'
      }).done(function (r) {
        if (r.success) {
          swalOk(r.message || 'Coleta finalizada!', function () {
            window.location = r.redirect || ((typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '') + '/painel/coletas');
          });
        } else {
          swalErr(r.message);
        }
      }).fail(function () {
        swalErr('Erro ao finalizar.');
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
      $.post(baseUrl(), { acao: 'cancelar', _csrf: csrf() }, function (r) {
        r = typeof r === 'object' ? r : JSON.parse(r);
        if (r.redirect) window.location = r.redirect;
      }, 'json');
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
})();
