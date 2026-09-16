/**
 * Gráficos Chart.js — dashboards admin e portal gerador.
 */
(function () {
  'use strict';

  function themeColors() {
    var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    return {
      text: dark ? '#C8E6C9' : '#5F6D61',
      grid: dark ? 'rgba(200,230,201,0.12)' : 'rgba(26,36,27,0.08)',
      primary: dark ? '#66BB6A' : '#2E7D32',
      primarySoft: dark ? 'rgba(102,187,106,0.25)' : 'rgba(46,125,50,0.15)',
      secondary: dark ? '#4DB6AC' : '#00897B',
      secondarySoft: dark ? 'rgba(77,182,172,0.25)' : 'rgba(0,137,123,0.15)',
      palette: dark
        ? ['#66BB6A', '#4DB6AC', '#29B6F6', '#FFB74D', '#BA68C8', '#EF5350']
        : ['#2E7D32', '#00897B', '#0288D1', '#E65100', '#7B1FA2', '#D32F2F'],
    };
  }

  function baseOptions(type) {
    var c = themeColors();
    var opts = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: { color: c.text, font: { size: 12 } },
        },
      },
    };
    if (type === 'bar' || type === 'line') {
      opts.scales = {
        x: {
          ticks: { color: c.text },
          grid: { color: c.grid },
        },
        y: {
          ticks: { color: c.text },
          grid: { color: c.grid },
          beginAtZero: true,
        },
      };
    }
    return opts;
  }

  function destroy(id) {
    var canvas = document.getElementById(id);
    if (canvas && canvas._wellChart) {
      canvas._wellChart.destroy();
      canvas._wellChart = null;
    }
  }

  function renderBar(id, labels, values, label, asCurrency) {
    if (typeof Chart === 'undefined') return;
    destroy(id);
    var canvas = document.getElementById(id);
    if (!canvas) return;
    var c = themeColors();
    canvas._wellChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: label,
          data: values,
          backgroundColor: c.primarySoft,
          borderColor: c.primary,
          borderWidth: 2,
          borderRadius: 6,
        }],
      },
      options: Object.assign(baseOptions('bar'), {
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: asCurrency ? {
              label: function (ctx) {
                var v = ctx.parsed.y || 0;
                return 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
              },
            } : {},
          },
        },
      }),
    });
  }

  function renderLine(id, labels, values, label, asCurrency) {
    if (typeof Chart === 'undefined') return;
    destroy(id);
    var canvas = document.getElementById(id);
    if (!canvas) return;
    var c = themeColors();
    canvas._wellChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: label,
          data: values,
          borderColor: c.secondary,
          backgroundColor: c.secondarySoft,
          fill: true,
          tension: 0.35,
          pointRadius: 4,
          pointBackgroundColor: c.secondary,
        }],
      },
      options: Object.assign(baseOptions('line'), {
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: asCurrency ? {
              label: function (ctx) {
                var v = ctx.parsed.y || 0;
                return 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
              },
            } : {},
          },
        },
      }),
    });
  }

  function renderDoughnut(id, labels, values) {
    if (typeof Chart === 'undefined') return;
    destroy(id);
    var canvas = document.getElementById(id);
    if (!canvas) return;
    if (!labels.length || !values.length) {
      return;
    }
    var c = themeColors();
    canvas._wellChart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: values,
          backgroundColor: c.palette.slice(0, labels.length),
          borderWidth: 0,
        }],
      },
      options: Object.assign(baseOptions('doughnut'), {
        cutout: '62%',
        plugins: { legend: { position: 'bottom' } },
      }),
    });
  }

  function initFromDom() {
    document.querySelectorAll('[data-well-chart]').forEach(function (el) {
      var type = el.getAttribute('data-well-chart');
      var id = el.id;
      if (!id) return;
      var labels = JSON.parse(el.getAttribute('data-labels') || '[]');
      var values = JSON.parse(el.getAttribute('data-values') || '[]');
      var label = el.getAttribute('data-label') || '';
      var currency = el.getAttribute('data-currency') === '1';
      if (type === 'bar') renderBar(id, labels, values, label, currency);
      else if (type === 'line') renderLine(id, labels, values, label, currency);
      else if (type === 'doughnut') renderDoughnut(id, labels, values);
    });
  }

  document.addEventListener('DOMContentLoaded', initFromDom);
  document.addEventListener('painel-theme-change', function () {
    setTimeout(initFromDom, 50);
  });

  window.WellDashboardCharts = {
    renderBar: renderBar,
    renderLine: renderLine,
    renderDoughnut: renderDoughnut,
    refresh: initFromDom,
  };
})();
