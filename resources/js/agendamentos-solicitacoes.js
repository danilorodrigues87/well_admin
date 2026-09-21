(function () {
  'use strict';

  var solicPage = 1;

  function baseUrl() {
    if (typeof wellAppUrl === 'function') {
      return wellAppUrl(null, (window.CRUD && window.CRUD.baseUrl) ? window.CRUD.baseUrl : '/painel/agendamentos');
    }
    var root = (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '');
    var path = (window.CRUD && window.CRUD.baseUrl) ? window.CRUD.baseUrl : '/painel/agendamentos';
    return root + '/' + String(path).replace(/^\/+/, '');
  }

  function csrf() {
    var el = document.querySelector('#crud-form input[name="_csrf"]');
    return el ? el.value : '';
  }

  function post(acao, extra) {
    var fd = new FormData();
    fd.append('acao', acao);
    fd.append('_csrf', csrf());
    if (extra) {
      Object.keys(extra).forEach(function (k) {
        fd.append(k, extra[k]);
      });
    }
    return fetch(baseUrl(), { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
      return r.json();
    });
  }

  window.carregarSolicitacoes = function (page) {
    if (typeof page === 'number' && page > 0) solicPage = page;
    var status = document.getElementById('filtro-solic-status');
    post('list_solicitacoes', {
      page: String(solicPage),
      status: status ? status.value : 'pendente'
    }).then(function (data) {
      if (typeof data === 'string') {
        try { data = JSON.parse(data); } catch (e) { data = { success: false }; }
      }
      var tbody = document.getElementById('solic-tbody');
      var pag = document.getElementById('solic-pagination');
      if (!data || !data.success) {
        if (tbody) {
          tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Não foi possível carregar solicitações.</td></tr>';
        }
        return;
      }
      if (tbody) tbody.innerHTML = data.itens || '';
      if (pag) pag.innerHTML = data.pagination || '';
    }).catch(function () {
      var tbody = document.getElementById('solic-tbody');
      if (tbody) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Erro de comunicação ao carregar solicitações.</td></tr>';
      }
    });
  };

  window.loadPageSolicitacoes = function (page) {
    carregarSolicitacoes(page);
  };

  function fillAprovarModal(data) {
    document.getElementById('sol-aprov-id').value = data.id;
    document.getElementById('sol-aprov-cliente').textContent = data.cliente_nome + ' — ' + (data.tipo === 'extra' ? 'Coleta extra' : 'Inclusa no plano');
    var c = data.cota || {};
    document.getElementById('sol-aprov-cota').textContent = c.periodo_label
      ? 'Cota: ' + (c.usadas || 0) + ' usadas + ' + (c.pendentes || 0) + ' pendentes de ' + (c.limite || 0) + ' (' + c.periodo_label + ')'
      : '';
    document.getElementById('sol-aprov-data').value = data.data_desejada || '';
    var wrap = document.getElementById('wrap-sol-valor-extra');
    if (data.tipo === 'extra') {
      wrap.classList.remove('d-none');
    } else {
      wrap.classList.add('d-none');
      document.getElementById('sol-aprov-valor').value = '';
    }
    document.getElementById('sol-aprov-resposta').value = '';
  }

  window.abrirAprovarSolicitacao = function (id) {
    post('get_solicitacao', { id: String(id) }).then(function (data) {
      if (!data.success) {
        if (typeof Swal !== 'undefined') Swal.fire('Erro', data.message || 'Erro', 'error');
        return;
      }
      fillAprovarModal(data);
      bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSolicAprovar')).show();
    });
  };

  window.abrirRecusarSolicitacao = function (id) {
    document.getElementById('sol-recus-id').value = id;
    document.getElementById('sol-recus-resposta').value = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSolicRecusar')).show();
  };

  window.verSolicitacao = function (id) {
    post('get_solicitacao', { id: String(id) }).then(function (data) {
      if (!data.success || typeof Swal === 'undefined') return;
      var html = '<p><strong>Status:</strong> ' + data.status + '</p>'
        + '<p><strong>Motivo:</strong> ' + (data.motivo_gerador || '—') + '</p>'
        + (data.resposta_admin ? '<p><strong>Resposta:</strong> ' + data.resposta_admin + '</p>' : '');
      Swal.fire({ title: data.cliente_nome, html: html, icon: 'info' });
    });
  };

  document.addEventListener('DOMContentLoaded', function () {
    var filtro = document.getElementById('filtro-solic-status');
    if (filtro) {
      filtro.addEventListener('change', function () {
        carregarSolicitacoes(1);
      });
    }
    var tabBtn = document.querySelector('[data-bs-target="#tab-solicitacoes"]');
    if (tabBtn) {
      tabBtn.addEventListener('shown.bs.tab', function () {
        carregarSolicitacoes(1);
      });
    }
    var btnAprov = document.getElementById('btn-confirm-aprov');
    if (btnAprov) {
      btnAprov.addEventListener('click', function () {
        post('aprovar_solicitacao', {
          id: document.getElementById('sol-aprov-id').value,
          data_aprovada: document.getElementById('sol-aprov-data').value,
          valor_cobranca_extra: document.getElementById('sol-aprov-valor').value,
          resposta_admin: document.getElementById('sol-aprov-resposta').value
        }).then(function (data) {
          if (!data.success) {
            Swal.fire('Erro', data.message || 'Erro', 'error');
            return;
          }
          bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSolicAprovar')).hide();
          Swal.fire('Ok', data.message, 'success');
          carregarSolicitacoes(1);
        });
      });
    }
    var btnRec = document.getElementById('btn-confirm-recus');
    if (btnRec) {
      btnRec.addEventListener('click', function () {
        post('recusar_solicitacao', {
          id: document.getElementById('sol-recus-id').value,
          resposta_admin: document.getElementById('sol-recus-resposta').value
        }).then(function (data) {
          if (!data.success) {
            Swal.fire('Erro', data.message || 'Erro', 'error');
            return;
          }
          bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSolicRecusar')).hide();
          Swal.fire('Ok', data.message, 'success');
          carregarSolicitacoes(1);
        });
      });
    }
  });
})();
