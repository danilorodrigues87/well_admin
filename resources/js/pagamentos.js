(function () {
  function apiBase() {
    return (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '');
  }

  function apiUrl() {
    var path = (window.CRUD && window.CRUD.baseUrl ? window.CRUD.baseUrl : '/painel/pagamentos').replace(/^\/+/, '');
    return apiBase() + '/' + path;
  }

  function csrf() {
    var el = document.querySelector('input[name="_csrf"]');
    return el ? el.value : '';
  }

  function postData(extra) {
    var data = new FormData();
    data.append('_csrf', csrf());
    if (extra) {
      Object.keys(extra).forEach(function (k) {
        if (extra[k] !== undefined && extra[k] !== null) {
          data.append(k, extra[k]);
        }
      });
    }
    return data;
  }

  function swalError(msg) {
    Swal.fire({ title: 'Erro', text: msg || 'Operação falhou.', icon: 'error' });
  }

  function swalSuccess(msg, html) {
    Swal.fire({
      title: 'Pronto!',
      text: html ? undefined : (msg || 'Concluído.'),
      html: html || undefined,
      icon: 'success'
    });
  }

  function swalWarning(msg, html) {
    Swal.fire({
      title: 'Atenção',
      text: html ? undefined : (msg || ''),
      html: html || undefined,
      icon: 'warning'
    });
  }

  function swalConfirm(title, text) {
    return Swal.fire({
      title: title || 'Confirmar',
      text: text || '',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sim, emitir',
      cancelButtonText: 'Cancelar'
    });
  }

  function swalLoading(title) {
    Swal.fire({
      title: title || 'Processando...',
      text: 'Pressione Esc para cancelar a espera.',
      allowOutsideClick: true,
      allowEscapeKey: true,
      showConfirmButton: false,
      didOpen: function () { Swal.showLoading(); }
    });
  }

  function swalClose() {
    if (Swal.isVisible()) {
      Swal.close();
    }
  }

  function fetchWithTimeout(url, options, timeoutMs) {
    var ms = timeoutMs || 90000;
    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = setTimeout(function () {
      if (controller) controller.abort();
    }, ms);
    var opts = options || {};
    if (controller) {
      opts.signal = controller.signal;
    }
    return fetch(url, opts).finally(function () {
      clearTimeout(timer);
    }).catch(function (err) {
      if (err && err.name === 'AbortError') {
        throw new Error('Tempo esgotado aguardando o servidor. Verifique SMTP no .env ou tente novamente.');
      }
      throw err;
    });
  }

  function swalCopied() {
    Swal.fire({
      title: 'Copiado!',
      icon: 'success',
      timer: 1800,
      showConfirmButton: false
    });
  }

  function parseJsonResponse(r) {
    return r.text().then(function (text) {
      var json;
      try {
        json = JSON.parse(text);
      } catch (e) {
        console.error('Resposta inválida:', text);
        throw new Error('Resposta inválida do servidor (HTTP ' + r.status + ').');
      }
      return json;
    });
  }

  function setTableLoading(tbodyId, colSpan, message) {
    var tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    tbody.innerHTML =
      '<tr><td colspan="' + colSpan + '" class="text-center text-muted py-4">' +
      '<div class="spinner-border spinner-border-sm text-primary me-2" role="status" aria-hidden="true"></div>' +
      (message || 'Carregando...') +
      '</td></tr>';
  }

  function setBtnLoading(btnId, loading) {
    var btn = document.getElementById(btnId);
    if (!btn) return;
    if (loading) {
      if (!btn.dataset.originalHtml) {
        btn.dataset.originalHtml = btn.innerHTML;
      }
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Carregando...';
    } else {
      btn.disabled = false;
      if (btn.dataset.originalHtml) {
        btn.innerHTML = btn.dataset.originalHtml;
      }
    }
  }

  function renderResultadoLote(json) {
    var html = '<div class="alert alert-' + (json.success ? 'success' : 'warning') + '">' +
      (json.message || '') + '</div>';
    if (json.erros && json.erros.length) {
      html += '<ul class="small mb-0">';
      json.erros.forEach(function (e) {
        html += '<li><strong>' + e.cliente_nome + ':</strong> ' + e.error + '</li>';
      });
      html += '</ul>';
    }
    if (json.detalhes && json.detalhes.length) {
      html += '<ul class="small mb-0 mt-2">';
      json.detalhes.forEach(function (d) {
        html += '<li>' + d.cliente_nome;
        if (d.email_ok === true) html += ' — e-mail enviado';
        else if (d.email_ok === false) html += ' — falha e-mail: ' + (d.email_error || '');
        html += '</li>';
      });
      html += '</ul>';
    }
    var el = document.getElementById('resultado-lote');
    if (el) {
      el.innerHTML = html;
      el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    return html;
  }

  var relatorioPage = 1;

  function carregarRelatorio(page) {
    if (typeof page === 'number' && page > 0) {
      relatorioPage = page;
    } else {
      relatorioPage = 1;
    }

    setTableLoading('tbody-faturamento', 9, 'Carregando relatório de faturamento...');
    document.getElementById('relatorio-total').textContent = 'Carregando...';
    document.getElementById('relatorio-pagination').innerHTML = '';
    setBtnLoading('btn-carregar-relatorio', true);

    var data = postData({
      acao: 'relatorio',
      page: relatorioPage,
      competencia: document.getElementById('filtro-competencia').value,
      plano_id: document.getElementById('filtro-plano').value,
      situacao: document.getElementById('filtro-situacao').value,
      busca: document.getElementById('filtro-busca').value
    });

    fetch(apiUrl(), { method: 'POST', body: data, credentials: 'same-origin' })
      .then(parseJsonResponse)
      .then(function (json) {
        if (!json.success && json.message) {
          throw new Error(json.message);
        }
        document.getElementById('tbody-faturamento').innerHTML = json.itens || '';
        document.getElementById('relatorio-pagination').innerHTML = json.pagination || '';
        document.getElementById('relatorio-total').textContent =
          (json.total || 0) + ' cliente(s) na competência ' + (json.competencia || '') +
          (json.page && json.total > 0 ? ' — página ' + json.page : '');
        bindFaturamentoEvents();
        carregarExtrasCompetencia();
      })
      .catch(function (err) {
        document.getElementById('tbody-faturamento').innerHTML =
          '<tr><td colspan="9" class="text-center text-danger py-3">Erro ao carregar relatório.</td></tr>';
        document.getElementById('relatorio-total').textContent = '';
        document.getElementById('relatorio-pagination').innerHTML = '';
        swalError('Erro ao carregar relatório: ' + (err.message || 'falha de rede'));
      })
      .finally(function () {
        setBtnLoading('btn-carregar-relatorio', false);
      });
  }

  window.loadPageRelatorio = function (page) {
    carregarRelatorio(page);
  };

  function carregarExtrasCompetencia() {
    var tbody = document.getElementById('tbody-extras');
    var resumo = document.getElementById('extras-resumo');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-2">Carregando...</td></tr>';
    var data = postData({
      acao: 'extras_competencia',
      competencia: document.getElementById('filtro-competencia').value
    });
    fetch(apiUrl(), { method: 'POST', body: data, credentials: 'same-origin' })
      .then(parseJsonResponse)
      .then(function (json) {
        if (!json.success) return;
        tbody.innerHTML = json.itens || '';
        if (resumo) {
          resumo.textContent = (json.qtd || 0) + ' extra(s) — total ' + (json.total || 'R$ 0,00');
        }
      })
      .catch(function () {
        tbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center">Erro ao carregar extras.</td></tr>';
      });
  }

  var historicoPage = 1;

  function carregarHistorico(page) {
    if (typeof page === 'number' && page > 0) {
      historicoPage = page;
    } else {
      historicoPage = 1;
    }

    setTableLoading('tbody-historico', 7, 'Carregando cobranças emitidas...');
    document.getElementById('hist-total').textContent = 'Carregando...';
    document.getElementById('hist-pagination').innerHTML = '';
    setBtnLoading('btn-carregar-historico', true);

    var data = postData({
      acao: 'historico',
      page: historicoPage,
      competencia: document.getElementById('hist-competencia').value,
      status: document.getElementById('hist-status').value
    });

    fetch(apiUrl(), { method: 'POST', body: data, credentials: 'same-origin' })
      .then(parseJsonResponse)
      .then(function (json) {
        document.getElementById('tbody-historico').innerHTML = json.itens || '';
        document.getElementById('hist-pagination').innerHTML = json.pagination || '';
        document.getElementById('hist-total').textContent =
          (json.total || 0) + ' cobrança(s)' +
          (json.page && json.total > 0 ? ' — página ' + json.page : '');
        bindHistoricoEvents();
      })
      .catch(function (err) {
        document.getElementById('tbody-historico').innerHTML =
          '<tr><td colspan="7" class="text-center text-danger py-3">Erro ao carregar cobranças.</td></tr>';
        document.getElementById('hist-total').textContent = '';
        swalError('Erro ao carregar histórico: ' + (err.message || 'falha de rede'));
      })
      .finally(function () {
        setBtnLoading('btn-carregar-historico', false);
      });
  }

  window.loadPageHistorico = function (page) {
    carregarHistorico(page);
  };

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  }

  function formatNum(n, dec) {
    return Number(n || 0).toLocaleString('pt-BR', {
      minimumFractionDigits: dec,
      maximumFractionDigits: dec
    });
  }

  function buildDetalheHtml(clienteId, calculo) {
    var linhas = '';
    (calculo.itens || []).forEach(function (item) {
      var saldoTxt = formatNum(item.saldo, 3);
      if (item.saldo_info) {
        saldoTxt += ' <small class="text-muted">(' + escapeHtml(item.saldo_info) + ')</small>';
      }
      linhas += '<tr>' +
        '<td>' + escapeHtml(item.nome) + '</td>' +
        '<td class="text-end">' + formatNum(item.coletado, 3) + ' ' + escapeHtml(item.unidade) + '</td>' +
        '<td class="text-end">' + saldoTxt + '</td>' +
        '<td class="text-end">' + formatNum(item.excedente, 3) + '</td>' +
        '<td class="text-end">R$ ' + formatNum(item.valor, 2) + '</td>' +
        '</tr>';
      (item.origens || []).forEach(function (origem) {
        var dataLabel = origem.data ? origem.data.split('-').reverse().slice(0, 2).join('/') : '—';
        linhas += '<tr class="table-light">' +
          '<td colspan="2" class="small text-muted ps-4">↳ MTR ' + parseInt(origem.mtr, 10) + ' (' + dataLabel + ')</td>' +
          '<td class="text-end small text-muted">' + formatNum(origem.quantidade, 3) + ' ' + escapeHtml(item.unidade) + '</td>' +
          '<td colspan="2"></td></tr>';
      });
    });
    if (!linhas) {
      linhas = '<tr><td colspan="5" class="text-muted">Sem itens de plano/resíduo.</td></tr>';
    }

    var avisoColetas = (calculo.coletas_no_mes || 0) > 1
      ? '<p class="small text-muted mb-2"><i class="fas fa-info-circle"></i> ' +
        calculo.coletas_no_mes + ' coleta(s) finalizada(s) nesta competência — o total <strong>soma todas</strong>.</p>'
      : '';

    return '<tr class="detalhe-row" data-detalhe-cliente="' + clienteId + '">' +
      '<td colspan="9" class="bg-light">' + avisoColetas +
      '<table class="table table-sm mb-0"><thead><tr>' +
      '<th>Resíduo</th><th class="text-end">Coletado</th><th class="text-end">Saldo incl.</th>' +
      '<th class="text-end">Excedente</th><th class="text-end">Valor R$</th>' +
      '</tr></thead><tbody>' + linhas + '</tbody></table></td></tr>';
  }

  function bindFaturamentoEvents() {
    document.querySelectorAll('.btn-detalhe').forEach(function (btn) {
      btn.onclick = function () {
        var cid = btn.getAttribute('data-cliente-id');
        var competencia = document.getElementById('filtro-competencia').value;
        var existing = document.querySelector('.detalhe-row[data-detalhe-cliente="' + cid + '"]');
        if (existing) {
          existing.remove();
          return;
        }

        btn.disabled = true;
        fetch(apiUrl() + '/' + cid + '/detalhe?competencia=' + encodeURIComponent(competencia), {
          credentials: 'same-origin'
        })
          .then(parseJsonResponse)
          .then(function (json) {
            if (!json.success || !json.calculo) {
              throw new Error(json.message || 'Detalhe indisponível');
            }
            var tr = document.querySelector('#tbody-faturamento tr[data-cliente-id="' + cid + '"]');
            if (!tr) return;
            tr.insertAdjacentHTML('afterend', buildDetalheHtml(cid, json.calculo));
          })
          .catch(function (err) {
            swalError(err.message || 'Erro ao carregar detalhe.');
          })
          .finally(function () {
            btn.disabled = false;
          });
      };
    });

    var chkTodos = document.getElementById('chk-todos');
    if (chkTodos) {
      chkTodos.onchange = function () {
        document.querySelectorAll('.chk-cliente:not(:disabled)').forEach(function (c) {
          c.checked = chkTodos.checked;
        });
      };
    }
  }

  function bindHistoricoEvents() {
    document.querySelectorAll('.btn-copy').forEach(function (btn) {
      btn.onclick = function () {
        var text = btn.getAttribute('data-copy') || '';
        if (!text) return;
        navigator.clipboard.writeText(text).then(function () {
          swalCopied();
        }).catch(function () {
          swalError('Não foi possível copiar para a área de transferência.');
        });
      };
    });

    document.querySelectorAll('.btn-email').forEach(function (btn) {
      btn.onclick = function () {
        var id = btn.getAttribute('data-id');
        swalConfirm('Enviar boleto por e-mail?', 'O PDF e os dados de pagamento serão enviados ao cliente.').then(function (result) {
          if (!result.isConfirmed) return;

          var data = postData({});
          swalLoading('Enviando e-mail...');
          fetchWithTimeout(apiUrl() + '/' + id + '/email', { method: 'POST', body: data, credentials: 'same-origin' }, 90000)
            .then(parseJsonResponse)
            .then(function (json) {
              if (json.success) {
                swalSuccess(json.message || 'E-mail enviado com sucesso.');
                carregarHistorico(historicoPage);
              } else {
                swalError(json.message || 'Falha no envio do e-mail.');
              }
            })
            .catch(function (err) {
              swalError(err.message || 'Falha no envio do e-mail.');
            })
            .finally(function () {
              swalClose();
            });
        });
      };
    });

    document.querySelectorAll('.btn-sync').forEach(function (btn) {
      btn.onclick = function () {
        var id = btn.getAttribute('data-id');
        var data = postData({});
        btn.disabled = true;
        swalLoading('Consultando Banco Inter...');
        fetchWithTimeout(apiUrl() + '/' + id + '/sync', { method: 'POST', body: data, credentials: 'same-origin' }, 60000)
          .then(parseJsonResponse)
          .then(function (json) {
            if (json.success) {
              swalSuccess(json.message || 'Status sincronizado.');
              carregarHistorico(historicoPage);
            } else {
              swalError(json.message || 'Falha ao sincronizar.');
            }
          })
          .catch(function (err) {
            swalError(err.message || 'Falha ao sincronizar.');
          })
          .finally(function () {
            swalClose();
            btn.disabled = false;
          });
      };
    });

    document.querySelectorAll('.btn-baixa').forEach(function (btn) {
      btn.onclick = function () {
        var id = btn.getAttribute('data-id');
        Swal.fire({
          title: 'Baixa manual',
          text: 'Marcar esta cobrança como PAGA? Use somente se o pagamento já foi confirmado.',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Sim, dar baixa',
          cancelButtonText: 'Cancelar'
        }).then(function (result) {
          if (!result.isConfirmed) return;

          var data = postData({});
          swalLoading('Registrando baixa...');
          fetchWithTimeout(apiUrl() + '/' + id + '/baixa', { method: 'POST', body: data, credentials: 'same-origin' }, 30000)
            .then(parseJsonResponse)
            .then(function (json) {
              if (json.success) {
                swalSuccess(json.message || 'Baixa registrada.');
                carregarHistorico(historicoPage);
              } else {
                swalError(json.message || 'Falha na baixa manual.');
              }
            })
            .catch(function (err) {
              swalError(err.message || 'Falha na baixa manual.');
            })
            .finally(function () {
              swalClose();
            });
        });
      };
    });
  }

  function emitirLote() {
    if (document.getElementById('btn-emitir-lote').getAttribute('data-inter') !== '1') {
      swalError('Integração Inter não configurada.');
      return;
    }

    var selecionados = [];
    document.querySelectorAll('#tbody-faturamento tr[data-cliente-id]').forEach(function (tr) {
      var chk = tr.querySelector('.chk-cliente');
      if (!chk || !chk.checked || chk.disabled) return;
      var input = tr.querySelector('.input-valor-final');
      selecionados.push({
        cliente_id: tr.getAttribute('data-cliente-id'),
        valor_final: input ? input.value : '0'
      });
    });

    if (!selecionados.length) {
      swalWarning('Selecione ao menos um cliente.');
      return;
    }

    var valorMinimo = 2.5;
    var abaixoMinimo = selecionados.filter(function (item) {
      return parseFloat(String(item.valor_final).replace(',', '.')) < valorMinimo;
    });
    if (abaixoMinimo.length) {
      swalWarning('O Banco Inter exige valor mínimo de R$ 2,50 por boleto. Ajuste o valor final antes de emitir.');
      return;
    }

    swalConfirm('Emitir boletos?', selecionados.length + ' boleto(s) serão gerados via Banco Inter.').then(function (result) {
      if (!result.isConfirmed) return;

      var data = postData({
        acao: 'emitir',
        competencia: document.getElementById('filtro-competencia').value,
        data_vencimento: document.getElementById('lote-vencimento').value,
        enviar_email: document.getElementById('lote-enviar-email').checked ? '1' : '',
        lote_multa_tipo: document.getElementById('lote-multa-tipo').value,
        lote_multa_taxa: document.getElementById('lote-multa-taxa').value,
        lote_mora_tipo: document.getElementById('lote-mora-tipo').value,
        lote_mora_taxa: document.getElementById('lote-mora-taxa').value
      });

      selecionados.forEach(function (item, idx) {
        data.append('itens[' + idx + '][cliente_id]', item.cliente_id);
        data.append('itens[' + idx + '][valor_final]', item.valor_final);
      });

      document.getElementById('btn-emitir-lote').disabled = true;
      swalLoading('Gerando boleto(s)... Aguarde.');

      fetchWithTimeout(apiUrl(), { method: 'POST', body: data, credentials: 'same-origin' }, 120000)
        .then(parseJsonResponse)
        .then(function (json) {
          var htmlBlock = renderResultadoLote(json);
          if (json.success) {
            swalSuccess(json.message || 'Boleto(s) emitido(s).', htmlBlock);
            carregarRelatorio(relatorioPage);
            carregarHistorico();
          } else {
            swalWarning(json.message || 'Nenhum boleto emitido.', htmlBlock);
            carregarRelatorio(relatorioPage);
          }
        })
        .catch(function (err) {
          swalError(err.message || 'Falha ao emitir boletos.');
          console.error(err);
        })
        .finally(function () {
          swalClose();
          document.getElementById('btn-emitir-lote').disabled = false;
        });
    });
  }

  function initConfigForm() {
    var cfg = window.PAGAMENTOS_CFG || {};
    var form = document.getElementById('form-config-cobranca');
    if (!form) return;

    form.querySelector('[name="multa_tipo"]').value = cfg.multa_tipo || 'PERCENTUAL';
    form.querySelector('[name="mora_tipo"]').value = cfg.mora_tipo || 'TAXAMENSAL';

    document.getElementById('lote-multa-tipo').value = cfg.multa_tipo || 'PERCENTUAL';
    document.getElementById('lote-multa-taxa').value = cfg.multa_taxa || '2.00';
    document.getElementById('lote-mora-tipo').value = cfg.mora_tipo || 'TAXAMENSAL';
    document.getElementById('lote-mora-taxa').value = cfg.mora_taxa || '1.00';

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var data = new FormData(form);
      data.append('acao', 'config');
      fetch(apiUrl(), { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          document.getElementById('config-msg').textContent = json.message || '';
          if (json.resumo) {
            document.getElementById('resumo-multa-mora').textContent = json.resumo;
          }
          if (json.success) {
            window.PAGAMENTOS_CFG.multa_tipo = form.querySelector('[name="multa_tipo"]').value;
            window.PAGAMENTOS_CFG.multa_taxa = form.querySelector('[name="multa_taxa"]').value;
            window.PAGAMENTOS_CFG.mora_tipo = form.querySelector('[name="mora_tipo"]').value;
            window.PAGAMENTOS_CFG.mora_taxa = form.querySelector('[name="mora_taxa"]').value;
            document.getElementById('lote-multa-tipo').value = window.PAGAMENTOS_CFG.multa_tipo;
            document.getElementById('lote-multa-taxa').value = window.PAGAMENTOS_CFG.multa_taxa;
            document.getElementById('lote-mora-tipo').value = window.PAGAMENTOS_CFG.mora_tipo;
            document.getElementById('lote-mora-taxa').value = window.PAGAMENTOS_CFG.mora_taxa;
          }
        });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btn-carregar-relatorio').addEventListener('click', function () {
      carregarRelatorio(1);
    });
    ['filtro-competencia', 'filtro-plano', 'filtro-situacao'].forEach(function (id) {
      document.getElementById(id).addEventListener('change', function () {
        carregarRelatorio(1);
      });
    });
    var buscaTimer;
    document.getElementById('filtro-busca').addEventListener('input', function () {
      clearTimeout(buscaTimer);
      buscaTimer = setTimeout(function () {
        carregarRelatorio(1);
      }, 400);
    });
    document.getElementById('btn-carregar-historico').addEventListener('click', function () {
      carregarHistorico(1);
    });
    document.getElementById('hist-competencia').addEventListener('change', function () {
      carregarHistorico(1);
    });
    document.getElementById('hist-status').addEventListener('change', function () {
      carregarHistorico(1);
    });
    document.getElementById('btn-emitir-lote').addEventListener('click', emitirLote);
    initConfigForm();
    carregarRelatorio(1);
    carregarHistorico(1);
  });
})();
