(function () {
  'use strict';

  var tipoResiduoTomSelect = null;
  var rascunhoConferido = !!(window.COLETA_WIZARD && window.COLETA_WIZARD.rascunhoConferido);
  var assinaturaCtx = null;
  var assinaturaDrawing = false;

  function baseUrl() {
    if (typeof wellAppUrl === 'function') {
      return wellAppUrl(null, (window.CRUD && window.CRUD.baseUrl) ? window.CRUD.baseUrl : '/painel/coleta/nova');
    }
    var path = (window.CRUD && window.CRUD.baseUrl) ? window.CRUD.baseUrl.replace(/^\/+/, '') : 'painel/coleta/nova';
    return (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '') + '/' + path;
  }

  function csrf() {
    return document.querySelector('#crud-form input[name="_csrf"]')?.value
      || document.querySelector('#form-finalizar input[name="_csrf"]')?.value
      || document.querySelector('input[name="_csrf"]')?.value
      || '';
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

  function setRascunhoConferido(resumoHtml, evidenciasHtml) {
    rascunhoConferido = true;
    if (window.COLETA_WIZARD) {
      window.COLETA_WIZARD.rascunhoConferido = true;
    }
    $('#rascunho-resumo-wrap').removeClass('d-none');
    if (resumoHtml) {
      $('#resumo-rascunho').html(resumoHtml);
    }
    if (evidenciasHtml) {
      $('#evidencias-preview').html(evidenciasHtml);
    }
    $('#btn-concluir-relatorio').prop('disabled', false);
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

  function initAssinaturaCanvas() {
    var canvas = document.getElementById('assinatura-canvas');
    if (!canvas) {
      return;
    }
    assinaturaCtx = canvas.getContext('2d');
    assinaturaCtx.strokeStyle = '#111';
    assinaturaCtx.lineWidth = 2;
    assinaturaCtx.lineCap = 'round';

    function pos(e) {
      var rect = canvas.getBoundingClientRect();
      var clientX = e.touches ? e.touches[0].clientX : e.clientX;
      var clientY = e.touches ? e.touches[0].clientY : e.clientY;
      return {
        x: (clientX - rect.left) * (canvas.width / rect.width),
        y: (clientY - rect.top) * (canvas.height / rect.height)
      };
    }

    function start(e) {
      assinaturaDrawing = true;
      var p = pos(e);
      assinaturaCtx.beginPath();
      assinaturaCtx.moveTo(p.x, p.y);
      e.preventDefault();
    }

    function move(e) {
      if (!assinaturaDrawing) return;
      var p = pos(e);
      assinaturaCtx.lineTo(p.x, p.y);
      assinaturaCtx.stroke();
      e.preventDefault();
    }

    function end() {
      assinaturaDrawing = false;
    }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', end);
    canvas.addEventListener('mouseleave', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);
  }

  window.limparAssinatura = function () {
    var canvas = document.getElementById('assinatura-canvas');
    if (canvas && assinaturaCtx) {
      assinaturaCtx.clearRect(0, 0, canvas.width, canvas.height);
    }
  };

  window.salvarAssinatura = function () {
    var canvas = document.getElementById('assinatura-canvas');
    if (!canvas) return;
    var dataUrl = canvas.toDataURL('image/png');
    swalLoading('Salvando assinatura…');
    $.post(baseUrl(), {
      acao: 'salvar_assinatura',
      assinatura_data_url: dataUrl,
      _csrf: csrf()
    }, function (r) {
      r = parseJsonResp(r);
      if (typeof Swal !== 'undefined') Swal.close();
      if (r.success) {
        if (r.preview_html) {
          $('#assinatura-preview-wrap').html(r.preview_html);
        }
        swalOk(r.message || 'Assinatura salva.');
      } else {
        swalErr(r.message);
      }
    }, 'json').fail(function () {
      if (typeof Swal !== 'undefined') Swal.close();
      swalErr('Erro ao salvar assinatura.');
    });
  };

  function reloadColetores() {
    var transportadoraId = $('#transportadora_id').val();
    var selected = $('#motorista_coletor_id').val();
    if ($('#motorista_coletor_id').prop('disabled')) {
      return;
    }
    $.post(baseUrl(), {
      acao: 'coletores_transportadora',
      transportadora_id: transportadoraId,
      selected_id: selected,
      _csrf: csrf()
    }, function (r) {
      r = parseJsonResp(r);
      if (r.success && r.options_html) {
        $('#motorista_coletor_id').html(r.options_html);
      }
    }, 'json');
  }

  window.salvarTransporte = function () {
    swalLoading('Salvando…', 'Aguarde um instante.');
    var data = $('#form-transporte').serializeArray();
    data.push({ name: 'acao', value: 'salvar_etapa_transporte' });
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
      swalErr('Erro ao salvar transportadora.');
    });
  };

  window.salvarDestinador = function () {
    swalLoading('Salvando destinador…');
    var data = $('#form-destinador').serializeArray();
    data.push({ name: 'acao', value: 'salvar_etapa_destinador' });
    data.push({ name: '_csrf', value: csrf() });
    $.post(baseUrl(), data, function (r) {
      r = parseJsonResp(r);
      if (typeof Swal !== 'undefined') Swal.close();
      if (r.success) {
        swalOk(r.message || 'Destinador salvo.');
      } else {
        swalErr(r.message);
      }
    }, 'json').fail(function () {
      if (typeof Swal !== 'undefined') Swal.close();
      swalErr('Erro ao salvar destinador.');
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

  window.salvarRascunhoFinal = function () {
    swalLoading(
      'Salvando relatório…',
      'Enviando fotos e texto.<br><small class="text-muted">Aguarde — pode demorar com fotos grandes.</small>'
    );

    var fd = new FormData(document.getElementById('form-finalizar'));
    fd.append('acao', 'salvar_rascunho_final');
    fd.append('_csrf', csrf());

    $.ajax({
      url: baseUrl(),
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json',
      timeout: 180000
    }).done(function (r) {
      r = parseJsonResp(r);
      if (typeof Swal !== 'undefined') Swal.close();
      if (r.success) {
        setRascunhoConferido(r.resumo_html, r.evidencias_html);
        swalOk(r.message || 'Relatório salvo!');
        document.getElementById('form-finalizar').querySelectorAll('input[type="file"]').forEach(function (inp) {
          inp.value = '';
        });
      } else {
        swalErr(r.message || 'Não foi possível salvar.');
      }
    }).fail(function (xhr) {
      if (typeof Swal !== 'undefined') Swal.close();
      var msg = 'Erro ao salvar relatório.';
      if (xhr.responseJSON && xhr.responseJSON.message) {
        msg = xhr.responseJSON.message;
      } else if (xhr.status === 0 || xhr.statusText === 'timeout') {
        msg = 'Upload demorou demais. Tente fotos menores ou apenas o relatório.';
      }
      swalErr(msg);
    });
  };

  window.concluirRelatorio = function () {
    if (!rascunhoConferido) {
      swalErr('Salve o relatório na etapa 3 antes de concluir.');
      bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#tab-relatorio"]')).show();
      return;
    }

    var dataReceb = document.querySelector('#form-destinador input[name="data_recebimento"]')?.value || '';
    if (!dataReceb) {
      swalErr('Informe a data de encerramento na etapa 4 e clique em "Salvar destinador".');
      bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#tab-destinador"]')).show();
      return;
    }

    var executar = function () {
      swalLoading('Concluindo relatório…', 'Gerando número de relatório interno.');

      $.ajax({
        url: baseUrl(),
        method: 'POST',
        data: {
          acao: 'finalizar',
          _csrf: csrf()
        },
        dataType: 'json',
        timeout: 30000
      }).done(function (r) {
        r = parseJsonResp(r);
        if (typeof Swal !== 'undefined') Swal.close();
        if (r.success) {
          swalOk(r.message || 'Relatório concluído!', function () {
            window.location = r.redirect || ((typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '') + '/painel/coletas');
          });
        } else {
          swalErr(r.message || 'Não foi possível concluir.');
        }
      }).fail(function (xhr) {
        if (typeof Swal !== 'undefined') Swal.close();
        var msg = 'Erro ao concluir relatório.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        swalErr(msg);
      });
    };

    salvarDestinadorSilent(executar);
  };

  function salvarDestinadorSilent(then) {
    var data = $('#form-destinador').serializeArray();
    data.push({ name: 'acao', value: 'salvar_etapa_destinador' });
    data.push({ name: '_csrf', value: csrf() });
    $.post(baseUrl(), data, function (r) {
      r = parseJsonResp(r);
      if (r.success && then) {
        then();
      } else if (!r.success) {
        swalErr(r.message || 'Salve o destinador antes de concluir.');
      }
    }, 'json').fail(function () {
      swalErr('Erro ao salvar destinador.');
    });
  }

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
    initAssinaturaCanvas();
    $('#transportadora_id').on('change', reloadColetores);
    document.querySelector('[data-bs-target="#tab-residuos"]')?.addEventListener('shown.bs.tab', function () {
      if (tipoResiduoTomSelect) {
        tipoResiduoTomSelect.focus();
      }
    });
  });
})();
