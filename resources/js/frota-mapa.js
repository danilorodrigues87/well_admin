/** Mapa da frota — polling de posições GPS */
(function () {
  'use strict';

  var cfg = window.WELL_FROTA_MAPA || {};
  var map = null;
  var markers = [];
  var pollTimer = null;

  function csrf() {
    var el = document.getElementById('frota-csrf')
      || document.querySelector('#well-csrf-holder input[name="_csrf"]')
      || document.querySelector('input[name="_csrf"]');
    return el ? el.value : '';
  }

  function postJson(payload) {
    var fd = new FormData();
    Object.keys(payload).forEach(function (k) {
      fd.append(k, payload[k]);
    });
    fd.append('_csrf', csrf());
    return fetch(cfg.baseUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function clearMarkers() {
    markers.forEach(function (m) { m.setMap(null); });
    markers = [];
  }

  function initMap() {
    if (map) {
      return;
    }
    var lat = parseFloat(document.getElementById('frota-fallback-lat').value, 10) || -23.5505;
    var lng = parseFloat(document.getElementById('frota-fallback-lng').value, 10) || -46.6333;
    map = new google.maps.Map(document.getElementById('frota-mapa'), {
      center: { lat: lat, lng: lng },
      zoom: 11,
      mapTypeControl: false,
      streetViewControl: false
    });
  }

  function renderPosicoes(posicoes) {
    if (!map) {
      return;
    }
    clearMarkers();
    var bounds = new google.maps.LatLngBounds();
    var resumo = document.getElementById('frota-resumo');
    var lista = document.getElementById('frota-lista-coletores');

    if (!posicoes.length) {
      resumo.textContent = 'Nenhuma posição recente. Peça ao coletor para ativar “Compartilhar localização” na Rota do dia.';
      lista.classList.add('d-none');
      return;
    }

    resumo.textContent = posicoes.length + ' coletor(es) com sinal nos últimos minutos · atualização automática a cada '
      + Math.round((cfg.pollMs || 15000) / 1000) + 's';

    lista.classList.remove('d-none');
    lista.innerHTML = '';

    posicoes.forEach(function (p) {
      var pos = { lat: p.latitude, lng: p.longitude };
      bounds.extend(pos);
      var marker = new google.maps.Marker({
        map: map,
        position: pos,
        title: p.usuario_nome,
        label: (p.usuario_nome || '?').charAt(0).toUpperCase()
      });
      markers.push(marker);

      var when = p.registrado_em ? new Date(p.registrado_em.replace(' ', 'T')).toLocaleString('pt-BR') : '—';
      var li = document.createElement('li');
      li.className = 'list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2';
      li.innerHTML =
        '<div><strong>' + escapeHtml(p.usuario_nome) + '</strong>'
        + ' <span class="badge bg-secondary">' + escapeHtml(p.fonte || 'web') + '</span>'
        + '<div class="small text-muted">Último sinal: ' + when + '</div></div>'
        + '<a class="btn btn-sm btn-outline-primary" href="' + (typeof url_base !== 'undefined' ? url_base : '') + '/painel/rota-do-dia">Ver rota</a>';
      lista.appendChild(li);
    });

    if (!bounds.isEmpty()) {
      map.fitBounds(bounds, 64);
    }
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function carregar() {
    var minutos = document.getElementById('frota-minutos').value;
    return postJson({ acao: 'posicoes', minutos: minutos }).then(function (data) {
      if (!data.success) {
        throw new Error(data.message || 'Erro ao carregar posições');
      }
      renderPosicoes(data.posicoes || []);
    }).catch(function (err) {
      document.getElementById('frota-resumo').textContent = err.message || 'Falha ao carregar.';
    });
  }

  function startPoll() {
    if (pollTimer) {
      clearInterval(pollTimer);
    }
    pollTimer = setInterval(carregar, cfg.pollMs || 15000);
  }

  function loadMaps(cb) {
    if (document.getElementById('frota-maps-configured').value !== '1') {
      document.getElementById('frota-alerta-maps').classList.remove('d-none');
      return;
    }
    if (window.google && window.google.maps) {
      cb();
      return;
    }
    var s = document.createElement('script');
    s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(cfg.mapsKey || '');
    s.async = true;
    s.onload = cb;
    document.head.appendChild(s);
  }

  document.addEventListener('DOMContentLoaded', function () {
    var csrfEl = document.querySelector('#well-csrf-holder input[name="_csrf"]');
    if (csrfEl) {
      document.getElementById('frota-csrf').value = csrfEl.value;
    }
    document.getElementById('btn-frota-recarregar').addEventListener('click', carregar);
    document.getElementById('frota-minutos').addEventListener('change', carregar);

    loadMaps(function () {
      initMap();
      carregar();
      startPoll();
    });
  });

  window.addEventListener('beforeunload', function () {
    if (pollTimer) {
      clearInterval(pollTimer);
    }
  });
})();
