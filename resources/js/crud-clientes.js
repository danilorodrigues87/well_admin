/** Clientes — ViaCEP + portal gerador (1 login por cliente) */
(function () {
  'use strict';

  function apiUrl() {
    var base = (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '');
    if (typeof listagem !== 'undefined' && listagem) {
      return base + '/' + String(listagem).replace(/^\/+/, '');
    }
    if (window.CRUD && window.CRUD.baseUrl) {
      return base + '/' + String(window.CRUD.baseUrl).replace(/^\/+/, '');
    }
    return base + '/painel/clientes';
  }

  function getCsrf() {
    var el = document.querySelector('#portal-form input[name="_csrf"]')
      || document.querySelector('#crud-form input[name="_csrf"]')
      || document.querySelector('input[name="_csrf"]');
    return el ? el.value : '';
  }

  function parseResp(resp) {
    if (typeof resp === 'object') {
      return resp;
    }
    try {
      return JSON.parse(resp);
    } catch (e) {
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

  function digits(v) {
    return String(v || '').replace(/\D/g, '');
  }

  function formatCep(v) {
    var d = digits(v).slice(0, 8);
    return d.length <= 5 ? d : d.slice(0, 5) + '-' + d.slice(5);
  }

  function showPortalModal() {
    var el = document.getElementById('portalModal');
    if (!el || typeof bootstrap === 'undefined') {
      swalError('Recarregue a página (Ctrl+F5) e tente novamente.');
      return false;
    }
    bootstrap.Modal.getOrCreateInstance(el).show();
    return true;
  }

  function preencherPortalForm(data) {
    var defaults = data.defaults || {};
    var usuario = data.usuario || null;

    document.getElementById('portal-id').value = usuario ? usuario.id : '0';
    document.getElementById('portal-nome').value = usuario ? usuario.nome : (defaults.nome || '');
    document.getElementById('portal-email').value = usuario ? usuario.email : (defaults.email || '');
    document.getElementById('portal-ativo').value = usuario ? (usuario.ativo ? '1' : '0') : '1';
    document.getElementById('portal-senha').value = '';

    var ultimo = document.getElementById('portal-ultimo-login');
    if (ultimo) {
      ultimo.textContent = usuario && usuario.ultimo_login
        ? 'Último login: ' + usuario.ultimo_login
        : (usuario ? 'Ainda não fez login.' : 'Acesso ainda não criado — salve para liberar o portal.');
    }

    var btnReset = document.getElementById('btn-portal-reset');
    if (btnReset) {
      btnReset.disabled = !usuario;
    }
  }

  function carregarPortalAcesso(clienteId) {
    var body = new URLSearchParams();
    body.append('acao', 'portal_acesso');
    body.append('cliente_id', clienteId);
    body.append('_csrf', getCsrf());

    return fetch(apiUrl(), {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (r) { return r.text(); })
      .then(function (text) {
        var data = parseResp(text);
        if (!data.success) {
          throw new Error(data.message || 'Falha ao carregar acesso.');
        }
        preencherPortalForm(data);
        if (data.portal_url) {
          var link = document.getElementById('portal-url-link');
          if (link) {
            link.href = data.portal_url;
            link.textContent = data.portal_url;
          }
        }
      });
  }

  function abrirPortalUsuarios(clienteId, clienteNome) {
    document.getElementById('portal-cliente-id').value = clienteId;
    document.getElementById('portal-form-cliente-id').value = clienteId;
    document.getElementById('portal-cliente-nome').textContent = clienteNome || ('Cliente #' + clienteId);
    if (!showPortalModal()) {
      return;
    }
    carregarPortalAcesso(clienteId).catch(function (err) {
      swalError(err.message || 'Erro ao carregar portal.');
    });
  }

  function salvarPortalUsuario() {
    var body = new URLSearchParams();
    body.append('acao', 'salvar_portal_usuario');
    body.append('id', document.getElementById('portal-id').value);
    body.append('cliente_id', document.getElementById('portal-cliente-id').value);
    body.append('nome', document.getElementById('portal-nome').value);
    body.append('email', document.getElementById('portal-email').value);
    body.append('senha', document.getElementById('portal-senha').value);
    body.append('ativo', document.getElementById('portal-ativo').value);
    body.append('_csrf', getCsrf());

    fetch(apiUrl(), {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (r) { return r.text(); })
      .then(function (text) {
        var data = parseResp(text);
        if (!data.success) {
          throw new Error(data.message || 'Erro ao salvar.');
        }
        return carregarPortalAcesso(document.getElementById('portal-cliente-id').value);
      })
      .then(function () {
        if (typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'success', title: 'Salvo', text: 'Login disponível em /gerador', timer: 2200, showConfirmButton: false });
        }
      })
      .catch(function (err) {
        swalError(err.message || 'Erro ao salvar acesso.');
      });
  }

  function resetarSenhaPortal() {
    if (!confirm('Redefinir senha para 12345678?')) {
      return;
    }
    var body = new URLSearchParams();
    body.append('acao', 'resetar_senha_portal');
    body.append('cliente_id', document.getElementById('portal-cliente-id').value);
    body.append('_csrf', getCsrf());

    fetch(apiUrl(), {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (r) { return r.text(); })
      .then(function (text) {
        var data = parseResp(text);
        if (!data.success) {
          throw new Error(data.message || 'Erro ao resetar senha.');
        }
        alert(data.message || 'Senha redefinida.');
      })
      .catch(function (err) {
        swalError(err.message || 'Erro ao resetar senha.');
      });
  }

  window.abrirPortalUsuarios = abrirPortalUsuarios;

  document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btn-portal-salvar')?.addEventListener('click', salvarPortalUsuario);
    document.getElementById('btn-portal-reset')?.addEventListener('click', resetarSenhaPortal);

    document.addEventListener('input', function (e) {
      if (e.target && e.target.id === 'crud-cep') {
        e.target.value = formatCep(e.target.value);
      }
    });

    document.addEventListener('blur', function (e) {
      if (!e.target || e.target.id !== 'crud-cep') {
        return;
      }
      var cep = digits(e.target.value);
      if (cep.length !== 8) {
        return;
      }
      var log = document.getElementById('crud-logradouro');
      var bairro = document.getElementById('crud-bairro');
      var cidade = document.getElementById('crud-cidade');
      var uf = document.getElementById('crud-uf');
      if (log) {
        log.disabled = true;
      }
      fetch('https://viacep.com.br/ws/' + cep + '/json/')
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.erro) {
            return;
          }
          if (data.logradouro && log) {
            log.value = data.logradouro;
          }
          if (data.bairro && bairro) {
            bairro.value = data.bairro;
          }
          if (data.localidade && cidade) {
            cidade.value = data.localidade;
          }
          if (data.uf && uf) {
            uf.value = data.uf;
          }
        })
        .catch(function () {})
        .finally(function () {
          if (log) {
            log.disabled = false;
          }
        });
    }, true);

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.btn-portal-acesso');
      if (!btn) {
        return;
      }
      e.preventDefault();
      abrirPortalUsuarios(btn.getAttribute('data-cliente-id'), btn.getAttribute('data-cliente-nome') || '');
    });
  });
})();
