/** Rota do dia — mapa Google + otimização */
(function () {
  'use strict';

  var cfg = window.WELL_ROTA_DIA || {};
  var map = null;
  var markers = [];
  var routePolyline = null;
  var paradas = [];
  var origin = null;
  var mapsReady = false;
  var gpsWatchId = null;
  var gpsShareTimer = null;
  var SHARE_KEY = 'well-rota-share-gps';

  function swalError(message, title) {
    var text = message || 'Operação não concluída.';
    if (typeof Swal !== 'undefined') {
      Swal.fire({ title: title || 'Erro', text: text, icon: 'error' });
    } else {
      alert(text);
    }
  }

  function swalInfo(message, title) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({ title: title || 'Atenção', text: message, icon: 'info' });
    } else {
      alert(message);
    }
  }

  function swalSuccess(message) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Ok',
        text: message,
        icon: 'success',
        timer: 2200,
        showConfirmButton: false
      });
    } else {
      alert(message);
    }
  }

  function paradaLatLng(p) {
    var lat = p.latitude != null ? p.latitude : p.lat;
    var lng = p.longitude != null ? p.longitude : p.lng;
    if (lat == null || lng == null || lat === '' || lng === '') {
      return null;
    }
    lat = parseFloat(lat);
    lng = parseFloat(lng);
    if (isNaN(lat) || isNaN(lng)) {
      return null;
    }
    return { lat: lat, lng: lng };
  }

  function paradaTemGps(p) {
    return paradaLatLng(p) !== null;
  }

  function csrfToken() {
    var el = document.querySelector('input[name="_csrf"]');
    return el ? el.value : '';
  }

  function coletorId() {
    var fixo = document.getElementById('rota-coletor-fixo');
    var sel = document.getElementById('rota-coletor-id');
    if (document.getElementById('rota-is-coletor').value === '1' && fixo) {
      return parseInt(fixo.value, 10) || 0;
    }
    return sel ? parseInt(sel.value, 10) || 0 : 0;
  }

  function dataRota() {
    var el = document.getElementById('rota-data');
    return el && el.value ? el.value : '';
  }

  function rotaFiltroId() {
    var el = document.getElementById('rota-filtro-id');
    if (!el || !el.value) return '';
    return el.value;
  }

  function postJson(acao, data) {
    var body = new FormData();
    body.append('acao', acao);
    body.append('_csrf', csrfToken());
    body.append('coletor_id', String(coletorId()));
    body.append('data', dataRota());
    var rid = rotaFiltroId();
    if (rid) body.append('rota_id', rid);
    Object.keys(data || {}).forEach(function (k) {
      var v = data[k];
      if (Array.isArray(v)) {
        v.forEach(function (item, i) {
          if (typeof item === 'object') {
            Object.keys(item).forEach(function (kk) {
              body.append(k + '[' + i + '][' + kk + ']', item[kk]);
            });
          } else {
            body.append(k + '[]', item);
          }
        });
      } else {
        body.append(k, v);
      }
    });
    return fetch(cfg.baseUrl, { method: 'POST', body: body }).then(function (r) { return r.json(); });
  }

  function loadGoogleMaps(key) {
    return new Promise(function (resolve, reject) {
      if (window.google && window.google.maps) {
        resolve();
        return;
      }
      var s = document.createElement('script');
      s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) + '&libraries=geometry';
      s.async = true;
      s.onload = function () { resolve(); };
      s.onerror = function () { reject(new Error('Falha ao carregar Google Maps')); };
      document.head.appendChild(s);
    });
  }

  function initMap() {
    if (!window.google || !window.google.maps) return;
    var center = origin || { lat: -23.5505, lng: -46.6333 };
    map = new google.maps.Map(document.getElementById('rota-mapa'), {
      center: center,
      zoom: 12,
      mapTypeControl: false
    });
    mapsReady = true;
    renderMap();
  }

  function clearMapOverlays() {
    markers.forEach(function (m) { m.setMap(null); });
    markers = [];
    if (routePolyline) {
      routePolyline.setMap(null);
      routePolyline = null;
    }
  }

  function renderMap() {
    if (!mapsReady || !map) return;
    clearMapOverlays();
    var bounds = new google.maps.LatLngBounds();

    if (origin) {
      var om = new google.maps.Marker({
        position: origin,
        map: map,
        title: 'Origem',
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 10,
          fillColor: '#0d6efd',
          fillOpacity: 1,
          strokeWeight: 2,
          strokeColor: '#fff'
        }
      });
      markers.push(om);
      bounds.extend(origin);
    }

    paradas.forEach(function (p, idx) {
      var ll = paradaLatLng(p);
      if (!ll) return;
      var pos = ll;
      var m = new google.maps.Marker({
        position: pos,
        map: map,
        label: String(p.ordem || idx + 1),
        title: p.nome_fantasia
      });
      markers.push(m);
      bounds.extend(pos);
    });

    if (window._rotaPolyline && google.maps.geometry && google.maps.geometry.encoding) {
      var path = google.maps.geometry.encoding.decodePath(window._rotaPolyline);
      routePolyline = new google.maps.Polyline({
        path: path,
        geodesic: true,
        strokeColor: '#198754',
        strokeOpacity: 0.9,
        strokeWeight: 4,
        map: map
      });
      path.forEach(function (pt) { bounds.extend(pt); });
    }

    if (!bounds.isEmpty()) {
      map.fitBounds(bounds, 48);
    }
  }

  function renderParadas() {
    var list = document.getElementById('rota-paradas-list');
    var loading = document.getElementById('rota-paradas-loading');
    var vazio = document.getElementById('rota-paradas-vazio');
    loading.classList.add('d-none');

    if (!paradas.length) {
      list.classList.add('d-none');
      vazio.classList.remove('d-none');
      if (window._rotaSemRota) {
        vazio.innerHTML = '<p class="mb-0 px-3">Este coletor não possui clientes atribuídos em nenhuma rota. Use <strong>Cadastros → Rotas → Atribuições</strong>.</p>';
      }
      return;
    }
    vazio.classList.add('d-none');
    list.classList.remove('d-none');
    list.innerHTML = '';

    paradas.forEach(function (p, idx) {
      var li = document.createElement('li');
      li.className = 'list-group-item';
      li.draggable = true;
      li.dataset.clienteId = String(p.cliente_id);
      li.dataset.index = String(idx);

      var prio = p.prioridade === 'urgente'
        ? '<span class="badge bg-danger ms-1">Urgente</span>' : '';
      var geo = !paradaTemGps(p)
        ? '<span class="badge bg-warning text-dark ms-1">Sem GPS</span>' : '';
      var st = p.status_parada || 'pendente';
      var stBadge = st === 'coletado'
        ? '<span class="badge bg-success ms-1">Coletado</span>'
        : (st === 'pulado'
          ? '<span class="badge bg-secondary ms-1">Pulado</span>'
          : '<span class="badge bg-light text-dark ms-1">Pendente</span>');
      var dataColeta = p.proxima_coleta
        ? new Date(p.proxima_coleta + 'T12:00:00').toLocaleDateString('pt-BR') : '—';
      var nav = p.maps_url
        ? '<a class="btn btn-sm btn-outline-secondary" href="' + p.maps_url + '" target="_blank" rel="noopener"><i class="fas fa-directions"></i></a> '
        : '';
      var coletar = '<a class="btn btn-sm btn-primary" href="' + cfg.urlColetaNova + '?cliente_id=' + p.cliente_id + '"><i class="fas fa-truck"></i></a>';
      var cid = String(p.cliente_id);
      var statusBtns =
        '<button type="button" class="btn btn-sm btn-outline-success btn-parada-status" data-cliente-id="' + cid + '" data-status="coletado" title="Marcar coletado"><i class="fas fa-check"></i></button> '
        + '<button type="button" class="btn btn-sm btn-outline-secondary btn-parada-status" data-cliente-id="' + cid + '" data-status="pulado" title="Pular"><i class="fas fa-forward"></i></button>';

      li.innerHTML =
        '<div class="d-flex align-items-start gap-2">' +
        '<span class="badge bg-secondary mt-1 ordem-badge">' + (p.ordem || idx + 1) + '</span>' +
        '<div class="flex-grow-1">' +
        '<div class="fw-semibold">' + escapeHtml(p.nome_fantasia) + prio + geo + stBadge + '</div>' +
        '<div class="small text-muted">' + escapeHtml(p.endereco || '') + '</div>' +
        '<div class="small">Próxima: ' + dataColeta + '</div>' +
        '</div>' +
        '<div class="btn-group well-btn-group-keep flex-wrap">' + nav + coletar + statusBtns + '</div>' +
        '</div>';

      li.addEventListener('dragstart', onDragStart);
      li.addEventListener('dragover', onDragOver);
      li.addEventListener('drop', onDrop);
      list.appendChild(li);
    });

    list.querySelectorAll('.btn-parada-status').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var clienteId = btn.getAttribute('data-cliente-id');
        var status = btn.getAttribute('data-status');
        postJson('parada_status', { cliente_id: clienteId, status: status }).then(function (data) {
          if (!data.success) throw new Error(data.message || 'Erro');
          paradas = data.paradas || paradas;
          renderParadas();
        }).catch(function (err) {
          swalError(err.message || 'Não foi possível atualizar o status.');
        });
      });
    });

    renumberParadas();
    renderMap();
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  var dragIndex = null;
  function onDragStart(e) {
    dragIndex = parseInt(e.currentTarget.dataset.index, 10);
    e.dataTransfer.effectAllowed = 'move';
  }
  function onDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
  }
  function onDrop(e) {
    e.preventDefault();
    var targetIndex = parseInt(e.currentTarget.dataset.index, 10);
    if (dragIndex === null || isNaN(targetIndex) || dragIndex === targetIndex) return;
    var item = paradas.splice(dragIndex, 1)[0];
    paradas.splice(targetIndex, 0, item);
    dragIndex = null;
    renderParadas();
  }

  function renumberParadas() {
    paradas.forEach(function (p, i) { p.ordem = i + 1; });
  }

  function setOrigin(lat, lng, label) {
    origin = { lat: lat, lng: lng };
    document.getElementById('rota-origin-info').textContent = 'Origem: ' + (label || (lat.toFixed(5) + ', ' + lng.toFixed(5)));
  }

  function carregarParadas() {
    document.getElementById('rota-paradas-loading').classList.remove('d-none');
    document.getElementById('rota-paradas-list').classList.add('d-none');
    document.getElementById('rota-paradas-vazio').classList.add('d-none');

    return postJson('paradas', {}).then(function (data) {
      if (!data.success) throw new Error(data.message || 'Erro ao carregar paradas');
      paradas = data.paradas || [];
      window._rotaPolyline = null;
      window._rotaSemRota = !!data.sem_rota;
      renderParadas();
    }).catch(function (err) {
      swalError(err.message || 'Erro ao carregar paradas');
    });
  }

  function usarGps() {
    if (!navigator.geolocation) {
      swalInfo('Geolocalização não disponível neste navegador.');
      return;
    }
    navigator.geolocation.getCurrentPosition(function (pos) {
      setOrigin(pos.coords.latitude, pos.coords.longitude, 'GPS atual');
      if (mapsReady) renderMap();
    }, function () {
      var lat = parseFloat(document.getElementById('rota-fallback-lat').value);
      var lng = parseFloat(document.getElementById('rota-fallback-lng').value);
      if (!isNaN(lat) && !isNaN(lng)) {
        setOrigin(lat, lng, 'Fallback (.env)');
        if (mapsReady) renderMap();
      } else {
        swalInfo('Não foi possível obter GPS. Configure MAPS_ORIGIN_FALLBACK_LAT/LNG no .env.');
      }
    }, { enableHighAccuracy: true, timeout: 15000 });
  }

  function otimizarRota() {
    if (!origin) {
      swalInfo('Defina a origem com "Usar minha localização" antes de otimizar.');
      return;
    }
    var ids = paradas.map(function (p) { return p.cliente_id; });
    document.getElementById('btn-rota-otimizar').disabled = true;
    postJson('otimizar', {
      origin_lat: origin.lat,
      origin_lng: origin.lng,
      cliente_ids: ids
    }).then(function (data) {
      if (!data.success) throw new Error(data.message || 'Erro ao otimizar');
      paradas = data.paradas || paradas;
      window._rotaPolyline = data.polyline || null;
      var resumo = '';
      if (data.distancia_metros) {
        resumo += 'Distância: ' + (data.distancia_metros / 1000).toFixed(1) + ' km. ';
      }
      if (data.duracao_segundos) {
        resumo += 'Tempo estimado: ' + Math.round(data.duracao_segundos / 60) + ' min.';
      }
      if (data.sem_coordenadas) {
        resumo += ' (' + data.sem_coordenadas + ' parada(s) sem coordenadas)';
      }
      document.getElementById('rota-resumo').textContent = resumo;
      renderParadas();
    }).catch(function (err) {
      swalError(err.message || 'Erro ao otimizar rota');
    }).finally(function () {
      document.getElementById('btn-rota-otimizar').disabled = false;
    });
  }

  function atualizarGps() {
    document.getElementById('btn-rota-geocode').disabled = true;
    postJson('geocode_paradas', {}).then(function (data) {
      if (!data.success) throw new Error(data.message || 'Erro ao geocodificar');
      paradas = data.paradas || paradas;
      window._rotaPolyline = null;
      renderParadas();
      if (data.message) {
        swalSuccess(data.message);
      }
    }).catch(function (err) {
      swalError(err.message || 'Erro ao atualizar GPS');
    }).finally(function () {
      document.getElementById('btn-rota-geocode').disabled = false;
    });
  }

  function enviarPosicao(pos) {
    return postJson('registrar_posicao', {
      latitude: pos.coords.latitude,
      longitude: pos.coords.longitude,
      accuracy_m: pos.coords.accuracy,
      heading: pos.coords.heading != null ? pos.coords.heading : '',
      speed_mps: pos.coords.speed != null ? pos.coords.speed : ''
    });
  }

  function stopGpsShare() {
    if (gpsWatchId != null && navigator.geolocation) {
      navigator.geolocation.clearWatch(gpsWatchId);
      gpsWatchId = null;
    }
    if (gpsShareTimer) {
      clearInterval(gpsShareTimer);
      gpsShareTimer = null;
    }
    var st = document.getElementById('rota-share-status');
    if (st) st.textContent = '';
  }

  function startGpsShare() {
    stopGpsShare();
    if (!navigator.geolocation) {
      swalInfo('Geolocalização indisponível neste dispositivo.');
      return;
    }
    var st = document.getElementById('rota-share-status');
    var onPos = function (pos) {
      if (st) {
        st.textContent = 'Último envio: ' + new Date().toLocaleTimeString('pt-BR')
          + ' · precisão ~' + Math.round(pos.coords.accuracy || 0) + ' m';
      }
      enviarPosicao(pos).catch(function () { /* silencioso */ });
    };
    var onErr = function () {
      if (st) st.textContent = 'Não foi possível obter GPS. Verifique permissões do navegador.';
    };
    gpsWatchId = navigator.geolocation.watchPosition(onPos, onErr, {
      enableHighAccuracy: true,
      maximumAge: 15000,
      timeout: 20000
    });
    gpsShareTimer = setInterval(function () {
      navigator.geolocation.getCurrentPosition(onPos, onErr, {
        enableHighAccuracy: true,
        maximumAge: 10000,
        timeout: 15000
      });
    }, 30000);
    navigator.geolocation.getCurrentPosition(onPos, onErr, { enableHighAccuracy: true });
  }

  function initGpsShareToggle() {
    var chk = document.getElementById('rota-share-gps');
    if (!chk) return;
    try {
      chk.checked = localStorage.getItem(SHARE_KEY) === '1';
    } catch (e) { /* ignore */ }
    if (chk.checked) {
      startGpsShare();
    }
    chk.addEventListener('change', function () {
      try {
        localStorage.setItem(SHARE_KEY, chk.checked ? '1' : '0');
      } catch (e) { /* ignore */ }
      if (chk.checked) {
        startGpsShare();
      } else {
        stopGpsShare();
      }
    });
  }

  function salvarOrdem() {
    renumberParadas();
    var ordem = paradas.map(function (p) {
      return { cliente_id: p.cliente_id, ordem: p.ordem };
    });
    postJson('salvar_ordem', { ordem: ordem }).then(function (data) {
      if (!data.success) throw new Error(data.message || 'Erro ao salvar');
      swalSuccess(data.message || 'Ordem salva.');
    }).catch(function (err) {
      swalError(err.message || 'Erro ao salvar ordem');
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('rota-maps-configured').value !== '1') {
      document.getElementById('rota-alerta-maps').classList.remove('d-none');
    }

    document.getElementById('btn-rota-gps').addEventListener('click', usarGps);
    document.getElementById('btn-rota-otimizar').addEventListener('click', otimizarRota);
    document.getElementById('btn-rota-salvar-ordem').addEventListener('click', salvarOrdem);
    document.getElementById('btn-rota-recarregar').addEventListener('click', carregarParadas);
    var btnGeo = document.getElementById('btn-rota-geocode');
    if (btnGeo) btnGeo.addEventListener('click', atualizarGps);

    var sel = document.getElementById('rota-coletor-id');
    if (sel) sel.addEventListener('change', carregarParadas);
    var dataEl = document.getElementById('rota-data');
    if (dataEl) dataEl.addEventListener('change', carregarParadas);
    var rotaEl = document.getElementById('rota-filtro-id');
    if (rotaEl) rotaEl.addEventListener('change', carregarParadas);

    var key = cfg.mapsKey;
    var start = function () {
      carregarParadas().then(function () {
        if (key) {
          return loadGoogleMaps(key).then(initMap);
        }
      }).catch(function () {});
    };

    initGpsShareToggle();
    start();
    usarGps();
  });

  window.addEventListener('beforeunload', stopGpsShare);
})();
