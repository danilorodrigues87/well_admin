/**
 * Well Eco Admin — listagem, filtros e CRUD AJAX (padrão painel CTI + acao Well)
 */
(function ($) {
  'use strict';

  var _listarReq = null;
  var _buscaTimer = null;

  function parseResp(resp) {
    if (resp === null || resp === undefined) {
      return { success: false, message: 'Resposta vazia.' };
    }
    if (typeof resp === 'object') {
      return resp;
    }
    try {
      return JSON.parse(resp);
    } catch (e) {
      console.error('JSON inválido:', resp);
      return { success: false, message: 'Resposta inválida do servidor.' };
    }
  }

  function swalError(msg) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({ title: 'Erro', text: msg || 'Operação falhou.', icon: 'error' });
    } else {
      alert(msg || 'Erro');
    }
  }

  function swalSuccess(msg, title) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: title || 'Pronto!',
        text: msg,
        icon: 'success',
        timer: 2200,
        showConfirmButton: false
      });
    }
  }

  function getCsrf() {
    var el = document.querySelector('#crud-form input[name="_csrf"]')
      || document.querySelector('input[name="_csrf"]');
    return el ? el.value : '';
  }

  function getApiUrl() {
    var base = (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '');
    if (typeof listagem !== 'undefined' && listagem) {
      return base + '/' + String(listagem).replace(/^\/+/, '');
    }
    if (window.CRUD && window.CRUD.baseUrl) {
      return base + '/' + String(window.CRUD.baseUrl).replace(/^\/+/, '');
    }
    return '';
  }

  /** Páginas com JS próprio (coleta selecionar, atribuições rota) não usam listar global */
  function shouldHandleCrudList() {
    if (!$('#crud-tbody').length) {
      return false;
    }
    if (window.ROTA_ATRIB) {
      return false;
    }
    if (window.CRUD && window.CRUD.autoLoad === false) {
      return false;
    }
    return getApiUrl() !== '';
  }

  /** Padrão CTI: todos input/select com name na barra de filtros */
  function coletarFiltrosBarra() {
    var data = {};
    var $bar = $('#barra-filtros-lista');
    if (!$bar.length) {
      $bar = $('.crud-filters-row').first();
    }
    $bar.find('input[name], select[name]').each(function () {
      var n = $(this).attr('name');
      if (n) {
        data[n] = $(this).val();
      }
    });
    return data;
  }

  window.getCrudFilterData = coletarFiltrosBarra;

  function setListLoading(loading) {
    var $btn = $('#btn-crud-buscar');
    if (loading) {
      var cols = $('#crud-tbody').closest('table').find('thead th').length || 7;
      $('#crud-tbody').html(
        '<tr><td colspan="' + cols + '" class="text-center text-muted py-4">' +
        '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Carregando...</td></tr>'
      );
      $('#crud-pagination').html('');
      if ($btn.length) {
        if (!$btn.data('orig-html')) {
          $btn.data('orig-html', $btn.html());
        }
        $btn.prop('disabled', true).html(
          '<span class="spinner-border spinner-border-sm me-1"></span> Buscando...'
        );
      }
    } else if ($btn.length && $btn.data('orig-html')) {
      $btn.prop('disabled', false).html($btn.data('orig-html'));
    }
  }

  function listar(filtro, page) {
    if (!shouldHandleCrudList()) {
      return;
    }
    var url = getApiUrl();

    page = page || 1;

    if (_listarReq && typeof _listarReq.abort === 'function') {
      try { _listarReq.abort(); } catch (e) {}
    }

    setListLoading(true);

    var payload = $.extend({
      acao: 'listar',
      page: page,
      _csrf: getCsrf()
    }, coletarFiltrosBarra());

    _listarReq = $.ajax({
      url: url,
      method: 'POST',
      data: payload,
      dataType: 'json',
      timeout: 30000
    }).done(function (result) {
      result = parseResp(result);
      if (result.itens !== undefined) {
        $('#crud-tbody').html(result.itens);
        $('#crud-pagination').html(result.pagination || '');
        if (typeof window.onCrudListLoaded === 'function') {
          window.onCrudListLoaded(result);
        }
      } else {
        swalError(result.message || 'Não foi possível carregar a lista.');
      }
    }).fail(function (xhr, status) {
      if (status === 'abort') {
        return;
      }
      console.error('listar fail', status, xhr.status, xhr.responseText);
      swalError('Falha ao carregar a lista (' + (xhr.status || status) + ').');
    }).always(function () {
      setListLoading(false);
    });
  }

  window.listar = listar;
  window.carregarLista = function (page) {
    listar(null, page || 1);
  };
  window.loadPage = function (page) {
    listar(null, page || 1);
  };
  window.irPagina = window.loadPage;

  window.novoRegistro = function () {
    var form = document.getElementById('crud-form');
    if (form) {
      form.reset();
    }
    $('#crud-id').val('0');
    if ($('#crud-senha-wrap').length) {
      $('#crud-senha-wrap').show();
    }
    var el = document.getElementById('crudModal');
    if (el && typeof bootstrap !== 'undefined') {
      bootstrap.Modal.getOrCreateInstance(el).show();
    }
  };

  window.editar = function (id) {
    var url = getApiUrl();
    if (!url) {
      return;
    }
    $.ajax({
      url: url,
      method: 'POST',
      data: { acao: 'get', id: id, _csrf: getCsrf() },
      dataType: 'json'
    }).done(function (resp) {
      var data = parseResp(resp);
      if (!data.success) {
        swalError(data.message || 'Erro ao carregar.');
        return;
      }
      $('#crud-id').val(data.id || 0);
      Object.keys(data).forEach(function (k) {
        var el = document.getElementById('crud-' + k);
        if (el) {
          el.value = data[k] !== null && data[k] !== undefined ? data[k] : '';
        }
      });
      if (data.nome_fantasia && $('#crud-nome-label').length) {
        $('#crud-nome-label').text(data.nome_fantasia);
      }
      if (data.nome && $('#crud-nome-label').length && !data.nome_fantasia) {
        $('#crud-nome-label').text(data.nome);
      }
      if ($('#crud-plano-info').length) {
        var planoInfo = data.plano_nome ? ('Plano: ' + data.plano_nome) : 'Sem plano';
        if (parseFloat(data.saldo_plano) > 0) {
          planoInfo += ' · Saldo incluso total: ' + Number(data.saldo_plano).toLocaleString('pt-BR') + ' kg';
        }
        $('#crud-plano-info').text(planoInfo);
      }
      if (data.modulos_html) {
        $('#crud-modulos').html(data.modulos_html);
      }
      if (data.classes_options) {
        $('#crud-classe_id').html(data.classes_options);
      }
      if (data.grupos_options) {
        $('#crud-grupo_id').html(data.grupos_options);
      }
      if ($('#crud-senha-wrap').length) {
        $('#crud-senha-wrap').toggle(!data.id);
      }
      var modal = document.getElementById('crudModal');
      if (modal && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modal).show();
      }
    }).fail(function (xhr) {
      swalError('Erro ao carregar registro (' + xhr.status + ').');
    });
  };

  window.salvarRegistro = function () {
    var url = getApiUrl();
    var form = document.getElementById('crud-form');
    if (!url || !form) {
      swalError('Formulário não encontrado.');
      return;
    }
    if (!form.reportValidity()) {
      return;
    }
    var formData = $(form).serializeArray();
    formData.push({ name: 'acao', value: 'salvar' });

    $.ajax({
      url: url,
      method: 'POST',
      data: formData,
      dataType: 'json'
    }).done(function (resp) {
      var data = parseResp(resp);
      if (!data.success) {
        swalError(data.message || 'Erro ao salvar.');
        return;
      }
      var modal = document.getElementById('crudModal');
      if (modal && typeof bootstrap !== 'undefined') {
        var inst = bootstrap.Modal.getInstance(modal);
        if (inst) {
          inst.hide();
        }
      }
      swalSuccess(data.message || 'Salvo com sucesso.');
      listar(null, 1);
    }).fail(function (xhr) {
      swalError('Erro ao salvar (' + xhr.status + ').');
    });
  };

  window.resetarSenha = function (id) {
    var url = getApiUrl();
    if (!url) {
      return;
    }

    var executar = function () {
      $.ajax({
        url: url,
        method: 'POST',
        data: { acao: 'resetar_senha', id: id, _csrf: getCsrf() },
        dataType: 'json'
      }).done(function (resp) {
        var data = parseResp(resp);
        if (!data.success) {
          swalError(data.message || 'Erro ao resetar senha.');
          return;
        }
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            title: 'Senha redefinida',
            text: data.message || 'Senha resetada com sucesso.',
            icon: 'success'
          });
        }
      }).fail(function (xhr) {
        swalError('Erro ao resetar senha (' + xhr.status + ').');
      });
    };

    if (typeof Swal === 'undefined') {
      if (confirm('Resetar senha deste funcionário para 12345678?')) {
        executar();
      }
      return;
    }

    Swal.fire({
      title: 'Resetar senha?',
      text: 'A senha será redefinida para 12345678. Informe o funcionário para alterá-la depois.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Sim, resetar',
      cancelButtonText: 'Cancelar'
    }).then(function (result) {
      if (result.isConfirmed) {
        executar();
      }
    });
  };

  window.excluir = function (id) {
    var url = getApiUrl();
    if (!url) {
      return;
    }

    var executar = function () {
      $.ajax({
        url: url,
        method: 'POST',
        data: { acao: 'excluir', id: id, _csrf: getCsrf() },
        dataType: 'json'
      }).done(function (resp) {
        var data = parseResp(resp);
        if (!data.success) {
          swalError(data.message || 'Erro ao desativar.');
          return;
        }
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            title: 'Desativado!',
            text: data.message || 'Registro desativado.',
            icon: 'success',
            timer: 2200,
            showConfirmButton: false
          });
        }
        listar(null, 1);
      }).fail(function (xhr) {
        swalError('Erro ao desativar (' + xhr.status + ').');
      });
    };

    if (typeof Swal === 'undefined') {
      if (confirm('Confirmar desativação?')) {
        executar();
      }
      return;
    }

    Swal.fire({
      title: 'Desativar registro?',
      text: 'O item permanece no sistema, mas deixa de aparecer nas listagens ativas. Você pode reativá-lo editando o cadastro.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Sim, desativar',
      cancelButtonText: 'Cancelar'
    }).then(function (result) {
      if (result.isConfirmed) {
        executar();
      }
    });
  };

  /* ── Eventos de filtros (padrão CTI) ── */
  $(document).on('change', '#barra-filtros-lista select[name], .crud-filters-row select[name], #barra-filtros-lista input[type="date"], .crud-filters-row input[type="date"]', function () {
    if (shouldHandleCrudList()) {
      listar(null, 1);
    }
  });

  $(document).on('click', '#btn-crud-buscar', function () {
    if (shouldHandleCrudList()) {
      listar(null, 1);
    }
  });

  $(document).on('input', '#barra-filtros-lista input[name="busca"], .crud-filters-row input[name="busca"]', function () {
    if (!shouldHandleCrudList()) {
      return;
    }
    clearTimeout(_buscaTimer);
    _buscaTimer = setTimeout(function () {
      listar(null, 1);
    }, 350);
  });

  $(document).on('keydown', '#barra-filtros-lista input[name="busca"], .crud-filters-row input[name="busca"]', function (e) {
    if (e.key === 'Enter' && shouldHandleCrudList()) {
      e.preventDefault();
      clearTimeout(_buscaTimer);
      listar(null, 1);
    }
  });

  $(document).on('click', '#barra-filtros-lista .btn-limpar-filtros, .crud-filters-row .btn-limpar-filtros', function (e) {
    e.preventDefault();
    if (!shouldHandleCrudList()) {
      return;
    }
    var $bar = $(this).closest('#barra-filtros-lista, .crud-filters-row');
    $bar.find('input[name="busca"]').val('');
    $bar.find('input[type="date"]').val('');
    $bar.find('select').each(function () {
      $(this).prop('selectedIndex', 0);
    });
    listar(null, 1);
  });

  $(function () {
    /* Aguarda {{scripts}} definir `listagem` / overrides de página */
    setTimeout(function () {
      if (shouldHandleCrudList()) {
        listar(null, 1);
      }
    }, 0);
  });
})(jQuery);
