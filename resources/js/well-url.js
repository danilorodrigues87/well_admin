/**
 * Monta URL absoluta do app (localhost em subpasta ou produção na raiz).
 * Depende de `url_base` (url-base.js ou var injetada pelo PHP).
 */
(function (global) {
  'use strict';

  function root() {
    return (typeof global.url_base !== 'undefined' ? global.url_base : '/').replace(/\/+$/, '');
  }

  /**
   * @param {string} path Ex.: /painel/coletas ou painel/coletas
   * @param {string} [crudBase] window.CRUD.baseUrl quando aplicável
   */
  function wellAppUrl(path, crudBase) {
    if (crudBase) {
      path = String(crudBase).replace(/^\/+/, '');
    } else if (path) {
      path = String(path).replace(/^\/+/, '');
    } else if (global.CRUD && global.CRUD.baseUrl) {
      path = String(global.CRUD.baseUrl).replace(/^\/+/, '');
    } else {
      return root() || '/';
    }
    return root() + '/' + path;
  }

  global.wellAppUrl = wellAppUrl;
  global.wellUrlRoot = root;
})(window);
