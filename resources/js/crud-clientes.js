/** Clientes — busca CEP (ViaCEP) */
(function ($) {
  'use strict';

  function digits(v) {
    return String(v || '').replace(/\D/g, '');
  }

  function formatCep(v) {
    var d = digits(v).slice(0, 8);
    if (d.length <= 5) {
      return d;
    }
    return d.slice(0, 5) + '-' + d.slice(5);
  }

  $(document).on('input', '#crud-cep', function () {
    this.value = formatCep(this.value);
  });

  $(document).on('blur', '#crud-cep', function () {
    var cep = digits($(this).val());
    if (cep.length !== 8) {
      return;
    }
    var $log = $('#crud-logradouro');
    var $bairro = $('#crud-bairro');
    var $cidade = $('#crud-cidade');
    var $uf = $('#crud-uf');
    $log.prop('disabled', true);
    fetch('https://viacep.com.br/ws/' + cep + '/json/')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.erro) {
          return;
        }
        if (data.logradouro) {
          $log.val(data.logradouro);
        }
        if (data.bairro) {
          $bairro.val(data.bairro);
        }
        if (data.localidade) {
          $cidade.val(data.localidade);
        }
        if (data.uf) {
          $uf.val(data.uf);
        }
      })
      .catch(function () {})
      .finally(function () {
        $log.prop('disabled', false);
      });
  });
})(jQuery);
