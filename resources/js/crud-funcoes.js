/** Override editar para modal de módulos por função */
(function () {
  window.novoRegistro = function () {};

  function swalErr(msg) {
    if (typeof Swal !== 'undefined') {
      Swal.fire('Erro', msg, 'error');
    } else {
      alert(msg);
    }
  }

  window.editar = function (id) {
    var base = (typeof url_base !== 'undefined' ? url_base : '/').replace(/\/+$/, '');
    $.ajax({
      url: base + '/painel/funcoes',
      method: 'POST',
      data: {
        acao: 'get',
        id: id,
        _csrf: document.querySelector('#crud-form input[name="_csrf"]')?.value || ''
      },
      dataType: 'json'
    }).done(function (resp) {
      var data = typeof resp === 'object' ? resp : JSON.parse(resp);
      if (!data.success) {
        swalErr(data.message || 'Erro');
        return;
      }
      document.getElementById('crud-id').value = data.id;
      document.getElementById('crud-nome-label').textContent = data.nome;
      document.getElementById('crud-modulos').innerHTML = data.modulos_html || '';
      bootstrap.Modal.getOrCreateInstance(document.getElementById('crudModal')).show();
    }).fail(function () {
      swalErr('Erro ao carregar função.');
    });
  };
})();
