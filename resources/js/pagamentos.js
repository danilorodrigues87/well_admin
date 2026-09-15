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

  function carregarRelatorio() {
    var data = postData({
      acao: 'relatorio',
      competencia: document.getElementById('filtro-competencia').value,
      plano_id: document.getElementById('filtro-plano').value,
      situacao: document.getElementById('filtro-situacao').value,
      busca: document.getElementById('filtro-busca').value
    });

    fetch(apiUrl(), { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (text) {
        var json;
        try { json = JSON.parse(text); } catch (e) {
          console.error('Resposta inválida:', text);
          throw new Error('Resposta inválida do servidor');
        }
        if (!json.success && json.message) {
          throw new Error(json.message);
        }
        document.getElementById('tbody-faturamento').innerHTML = json.itens || '';
        document.getElementById('relatorio-total').textContent =
          (json.total || 0) + ' cliente(s) na competência ' + (json.competencia || '');
        bindFaturamentoEvents();
      })
      .catch(function (err) {
        alert('Erro ao carregar relatório: ' + (err.message || 'falha de rede'));
      });
  }

  function carregarHistorico() {
    var data = postData({
      acao: 'historico',
      competencia: document.getElementById('hist-competencia').value,
      status: document.getElementById('hist-status').value
    });

    fetch(apiUrl(), { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        document.getElementById('tbody-historico').innerHTML = json.itens || '';
        bindHistoricoEvents();
      })
      .catch(function (err) {
        alert('Erro ao carregar histórico: ' + (err.message || 'falha de rede'));
      });
  }

  function bindFaturamentoEvents() {
    document.querySelectorAll('.btn-detalhe').forEach(function (btn) {
      btn.onclick = function () {
        var cid = btn.getAttribute('data-cliente-id');
        document.querySelectorAll('.detalhe-row[data-detalhe-cliente="' + cid + '"]').forEach(function (row) {
          row.classList.toggle('d-none');
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
          alert('Copiado!');
        });
      };
    });

    document.querySelectorAll('.btn-email').forEach(function (btn) {
      btn.onclick = function () {
        var id = btn.getAttribute('data-id');
        var data = postData({});
        fetch(apiUrl() + '/' + id + '/email', { method: 'POST', body: data, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (json) {
            alert(json.message || (json.success ? 'Enviado' : 'Erro'));
            if (json.success) carregarHistorico();
          });
      };
    });
  }

  function emitirLote() {
    if (document.getElementById('btn-emitir-lote').getAttribute('data-inter') !== '1') {
      alert('Integração Inter não configurada.');
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
      alert('Selecione ao menos um cliente.');
      return;
    }

    var valorMinimo = 2.5;
    var abaixoMinimo = selecionados.filter(function (item) {
      return parseFloat(String(item.valor_final).replace(',', '.')) < valorMinimo;
    });
    if (abaixoMinimo.length) {
      alert('O Banco Inter exige valor mínimo de R$ 2,50 por boleto. Ajuste o valor final antes de emitir.');
      return;
    }

    if (!confirm('Emitir ' + selecionados.length + ' boleto(s)?')) return;

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
    fetch(apiUrl(), { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        var html = '<div class="alert alert-' + (json.success ? 'success' : 'warning') + '">' +
          (json.message || '') + '</div>';
        if (json.erros && json.erros.length) {
          html += '<ul class="small">';
          json.erros.forEach(function (e) {
            html += '<li>' + e.cliente_nome + ': ' + e.error + '</li>';
          });
          html += '</ul>';
        }
        document.getElementById('resultado-lote').innerHTML = html;
        carregarRelatorio();
      })
      .finally(function () {
        document.getElementById('btn-emitir-lote').disabled = false;
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
    document.getElementById('btn-carregar-relatorio').addEventListener('click', carregarRelatorio);
    document.getElementById('btn-carregar-historico').addEventListener('click', carregarHistorico);
    document.getElementById('btn-emitir-lote').addEventListener('click', emitirLote);
    initConfigForm();
    carregarRelatorio();
  });
})();
