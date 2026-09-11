/**
 * CRUD AJAX — Well Eco Admin (filtros estilo CTI + SweetAlert2)
 */
(function ($) {
  'use strict';

  var _listarReq = null;
  var _buscaTimer = null;

  function parseResp(resp) {
    if (resp === null || resp === undefined) {
      return { success: false, message: 'Resposta vazia do servidor.' };
    }
    if (typeof resp === 'object') {
      return resp;
    }
    try {
      return JSON.parse(resp);
    } catch (e) {
      console.error('Resposta inválida:', resp);
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
      Swal.fire({ title: title || 'Pronto!', text: msg, icon: 'success', timer: 2200, showConfirmButton: false });
    }
  }

  function getCsrf() {
    var el = document.querySelector('#crud-form input[name="_csrf"]')
      || document.querySelector('input[name="_csrf"]');
    return el ? el.value : '';
  }

  function apiUrl() {
    var base = (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '');
    var path = (window.CRUD && window.CRUD.baseUrl ? window.CRUD.baseUrl : '').replace(/^\/+/, '');
    return base + '/' + path;
  }

  /** Coleta todos os filtros da barra (padrão CTI: input/select com name). */
  function getFilterBar() {
    var $bar = $('#barra-filtros-lista');
    if (!$bar.length) {
      $bar = $('.crud-filters-row').first();
    }
    return $bar;
  }

  function getFilterData() {
    var data = {};
    getFilterBar().find('input[name], select[name]').each(function () {
      var name = $(this).attr('name');
      if (name) {
        data[name] = $(this).val() || '';
      }
    });
    return data;
  }

  window.getCrudFilterData = getFilterData;

  function showModal() {
    var el = document.getElementById('crudModal');
    if (!el) {
      swalError('Modal não encontrado na página.');
      return;
    }
    if (typeof bootstrap === 'undefined') {
      swalError('Bootstrap não carregado. Recarregue a página.');
      return;
    }
    bootstrap.Modal.getOrCreateInstance(el).show();
  }

  function hideModal() {
    var el = document.getElementById('crudModal');
    if (el && typeof bootstrap !== 'undefined') {
      var inst = bootstrap.Modal.getInstance(el);
      if (inst) {
        inst.hide();
      }
    }
  }

  function loadPage(page) {
    if (!window.CRUD || !window.CRUD.baseUrl) {
      return;
    }
    page = page || 1;

    if (_listarReq && typeof _listarReq.abort === 'function') {
      try { _listarReq.abort(); } catch (e) {}
    }

    var payload = $.extend({
      acao: 'listar',
      page: page,
      _csrf: getCsrf()
    }, getFilterData());

    _listarReq = $.ajax({
      url: apiUrl(),
      method: 'POST',
      data: payload,
      dataType: 'json',
      timeout: 30000
    }).done(function (data) {
      data = parseResp(data);
      if (data.itens !== undefined) {
        $('#crud-tbody').html(data.itens);
        $('#crud-pagination').html(data.pagination || '');
      } else if (data.success === false && data.message) {
        console.warn('Listagem:', data.message);
      }
    }).fail(function (xhr, status) {
      if (status === 'abort') {
        return;
      }
      console.error('Erro ao listar', xhr.status, xhr.responseText);
      swalError('Erro ao carregar lista (' + (xhr.status || status) + ').');
    });
  }

  window.loadPage = loadPage;

  window.novoRegistro = function () {
    var form = document.getElementById('crud-form');
    if (form) {
      form.reset();
    }
    $('#crud-id').val('0');
    if ($('#crud-senha-wrap').length) {
      $('#crud-senha-wrap').show();
    }
    showModal();
  };

  window.editar = function (id) {
    $.ajax({
      url: apiUrl(),
      method: 'POST',
      data: { acao: 'get', id: id, _csrf: getCsrf() },
      dataType: 'json'
    }).done(function (resp) {
      var data = parseResp(resp);
      if (!data.success) {
        swalError(data.message || 'Erro ao carregar registro.');
        return;
      }
      $('#crud-id').val(data.id || 0);
      Object.keys(data).forEach(function (k) {
        var el = document.getElementById('crud-' + k);
        if (el) {
          el.value = data[k] !== null && data[k] !== undefined ? data[k] : '';
        }
      });
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
      showModal();
    }).fail(function (xhr) {
      swalError('Erro ao carregar registro (' + xhr.status + ').');
    });
  };

  window.salvarRegistro = function () {
    var form = document.getElementById('crud-form');
    if (!form) {
      swalError('Formulário não encontrado.');
      return;
    }
    if (!form.reportValidity()) {
      return;
    }
    var formData = $(form).serializeArray();
    formData.push({ name: 'acao', value: 'salvar' });

    $.ajax({
      url: apiUrl(),
      method: 'POST',
      data: formData,
      dataType: 'json'
    }).done(function (resp) {
      var data = parseResp(resp);
      if (!data.success) {
        swalError(data.message || 'Erro ao salvar.');
        return;
      }
      hideModal();
      swalSuccess(data.message || 'Salvo com sucesso.');
      loadPage(1);
    }).fail(function (xhr) {
      swalError('Erro ao salvar (' + xhr.status + ').');
    });
  };

  window.excluir = function (id) {
    var doDelete = function () {
      $.ajax({
        url: apiUrl(),
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
            text: data.message || 'Registro desativado com sucesso.',
            icon: 'success',
            timer: 2200,
            showConfirmButton: false
          });
        }
        loadPage(1);
      }).fail(function (xhr) {
        swalError('Erro ao desativar (' + xhr.status + ').');
      });
    };

    if (typeof Swal === 'undefined') {
      if (confirm('Confirmar desativação?')) {
        doDelete();
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
        doDelete();
      }
    });
  };

  function bindCrudEvents() {
    $(document).off('change.crud', '#barra-filtros-lista select[name], .crud-filters-row select[name]')
      .on('change.crud', '#barra-filtros-lista select[name], .crud-filters-row select[name]', function () {
        loadPage(1);
      });

    $(document).off('input.crud', '#barra-filtros-lista input[name="busca"], .crud-filters-row input[name="busca"]')
      .on('input.crud', '#barra-filtros-lista input[name="busca"], .crud-filters-row input[name="busca"]', function () {
        clearTimeout(_buscaTimer);
        _buscaTimer = setTimeout(function () {
          loadPage(1);
        }, 350);
      });

    $(document).off('keydown.crud', '#barra-filtros-lista input[name="busca"], .crud-filters-row input[name="busca"]')
      .on('keydown.crud', '#barra-filtros-lista input[name="busca"], .crud-filters-row input[name="busca"]', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          clearTimeout(_buscaTimer);
          loadPage(1);
        }
      });

    $(document).off('keydown.crud', '#crud-busca')
      .on('keydown.crud', '#crud-busca', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          clearTimeout(_buscaTimer);
          loadPage(1);
        }
      });
  }

  $(function () {
    bindCrudEvents();
    if (window.CRUD && window.CRUD.baseUrl && window.CRUD.autoLoad !== false) {
      loadPage(1);
    }
  });
})(jQuery);
