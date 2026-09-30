(function () {
    'use strict';

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function formatMoney(n) {
        return Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function parsePrefill(raw) {
        try {
            var arr = JSON.parse(raw || '[]');
            return Array.isArray(arr) ? arr.map(function (x) { return parseInt(x, 10); }) : [];
        } catch (e) {
            return [];
        }
    }

    function ContratoWizard(opts) {
        this.url = opts.url;
        this.prefillIds = opts.prefillIds || [];
        this.planoSelect = qs(opts.planoSelect);
        this.itensHost = qs(opts.itensHost);
        this.modeloInfo = qs(opts.modeloInfo);
        this.revisaoItens = qs(opts.revisaoItens);
        this.revisaoMensal = qs(opts.revisaoMensal);
        this.valorInput = qs(opts.valorInput);
        this.qtdMesesInput = qs(opts.qtdMesesInput);
        this.valorTouched = false;
        this.currentItens = [];
        this.initSteps(opts.form);
    }

    ContratoWizard.prototype.initSteps = function (formRef) {
        var self = this;
        var form = typeof formRef === 'string' ? qs(formRef) : formRef;
        if (!form) {
            return;
        }
        self.formEl = form;
        var steps = qsa('[data-wizard-step]', form);
        var panels = qsa('[data-wizard-panel]', form);
        var idx = 0;

        function showStep(i) {
            idx = Math.max(0, Math.min(steps.length - 1, i));
            steps.forEach(function (el, n) {
                el.classList.toggle('active', n === idx);
                el.classList.toggle('text-muted', n !== idx);
            });
            panels.forEach(function (el, n) {
                el.classList.toggle('d-none', n !== idx);
            });
        }

        qsa('[data-wizard-next]', form).forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (idx === 0) {
                    var planoSel = self.planoSelect;
                    if (!planoSel || !planoSel.value) {
                        alert('Selecione um plano para continuar.');
                        return;
                    }
                    if (!self.currentItens.length && self.itensHost) {
                        self.loadPlano(planoSel.value, self.prefillIds);
                    }
                }
                if (idx === 1 && !self.validateItens()) {
                    return;
                }
                if (idx === 2 && !self.validateComercial()) {
                    return;
                }
                if (idx === 2) {
                    self.renderRevisao();
                }
                showStep(idx + 1);
            });
        });
        qsa('[data-wizard-prev]', form).forEach(function (btn) {
            btn.addEventListener('click', function () {
                showStep(idx - 1);
            });
        });

        showStep(0);
    };

    ContratoWizard.prototype.loadPlano = function (planoId, prefillOverride) {
        var self = this;
        if (!planoId) {
            self.itensHost.innerHTML = '<p class="text-muted">Selecione um plano no passo anterior.</p>';
            return;
        }
        self.itensHost.innerHTML = '<p class="text-muted">Carregando itens do plano…</p>';
        fetch(self.url + '?plano_id=' + encodeURIComponent(planoId), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    self.itensHost.innerHTML = '<p class="alert alert-warning">' + (data.error || 'Erro ao carregar plano') + '</p>';
                    return;
                }
                self.currentItens = data.itens || [];
                if (self.modeloInfo && data.plano) {
                    self.modeloInfo.textContent = 'Modelo: ' + data.plano.contrato_modelo_label
                        + ' · Sugestão mensalidade plano: R$ ' + formatMoney(data.plano.valor_mensal);
                }
                if (self.qtdMesesInput && data.plano && !self.qtdMesesInput.dataset.userSet) {
                    self.qtdMesesInput.value = data.plano.default_meses;
                }
                if (self.valorInput && data.plano && !self.valorTouched && !self.valorInput.value) {
                    self.valorInput.value = formatMoney(data.plano.valor_mensal);
                }
                var prefill = prefillOverride && prefillOverride.length ? prefillOverride : self.prefillIds;
                var useAll = !prefill || prefill.length === 0;
                self.renderItensTable(useAll ? null : prefill);
            })
            .catch(function () {
                self.itensHost.innerHTML = '<p class="alert alert-danger">Falha ao carregar itens do plano.</p>';
            });
    };

    ContratoWizard.prototype.renderItensTable = function (selectedIds) {
        var self = this;
        var selectedMap = {};
        if (selectedIds) {
            selectedIds.forEach(function (id) { selectedMap[id] = true; });
        }
        if (!self.currentItens.length) {
            self.itensHost.innerHTML = '<div class="alert alert-warning">Este plano não tem itens de resíduo. Cadastre em <strong>Planos</strong> antes de gerar o contrato.</div>';
            return;
        }
        var html = '<p class="small text-muted">Marque os resíduos que este gerador utilizará. Franquia e excedente vêm do plano; a mensalidade é definida no próximo passo.</p>'
            + '<div class="mb-2">'
            + '<button type="button" class="btn btn-sm btn-outline-secondary me-1" data-itens-all>Marcar todos</button>'
            + '<button type="button" class="btn btn-sm btn-outline-secondary" data-itens-none>Desmarcar todos</button>'
            + '</div>'
            + '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0">'
            + '<thead><tr><th>Incluir</th><th>Resíduo</th><th>Franquia</th><th>Excedente</th><th>Obs.</th></tr></thead><tbody>';
        self.currentItens.forEach(function (item) {
            var checked = !selectedIds || selectedMap[item.tipo_residuo_id] ? ' checked' : '';
            html += '<tr><td><input type="checkbox" class="form-check-input" name="itens_tipo_residuo_id[]" value="'
                + item.tipo_residuo_id + '"' + checked + '></td>'
                + '<td>' + escapeHtml(item.tipo_nome) + '</td>'
                + '<td>' + formatMoney(item.saldo_incluso) + ' ' + escapeHtml(item.unidade) + '</td>'
                + '<td>R$ ' + formatMoney(item.valor_excedente) + '</td>'
                + '<td class="small">' + escapeHtml(item.flags_label) + '</td></tr>';
        });
        html += '</tbody></table></div>';
        self.itensHost.innerHTML = html;

        qs('[data-itens-all]', self.itensHost).addEventListener('click', function () {
            qsa('input[name="itens_tipo_residuo_id[]"]', self.itensHost).forEach(function (cb) { cb.checked = true; });
        });
        qs('[data-itens-none]', self.itensHost).addEventListener('click', function () {
            qsa('input[name="itens_tipo_residuo_id[]"]', self.itensHost).forEach(function (cb) { cb.checked = false; });
        });
    };

    ContratoWizard.prototype.validateItens = function () {
        if (!this.itensHost) {
            return true;
        }
        var host = this.itensHost;
        var checked = qsa('input[name="itens_tipo_residuo_id[]"]:checked', host);
        if (!checked.length) {
            var anyItem = qsa('input[name="itens_tipo_residuo_id[]"]', host);
            if (!anyItem.length) {
                alert('Este plano não possui itens de resíduo. Cadastre itens em Planos ou escolha outro plano.');
                return false;
            }
            alert('Selecione ao menos um tipo de resíduo para este gerador.');
            return false;
        }
        return true;
    };

    ContratoWizard.prototype.validateComercial = function () {
        var v = this.valorInput && this.valorInput.value.trim();
        if (!v) {
            alert('Informe o valor mensal negociado neste contrato.');
            return false;
        }
        return true;
    };

    ContratoWizard.prototype.renderRevisao = function () {
        var self = this;
        if (self.revisaoMensal && self.valorInput) {
            self.revisaoMensal.textContent = 'R$ ' + self.valorInput.value;
        }
        if (!self.revisaoItens) {
            return;
        }
        var ids = {};
        qsa('input[name="itens_tipo_residuo_id[]"]:checked', self.itensHost).forEach(function (cb) {
            ids[parseInt(cb.value, 10)] = true;
        });
        var lines = self.currentItens.filter(function (it) { return ids[it.tipo_residuo_id]; });
        if (!lines.length) {
            self.revisaoItens.innerHTML = '<p class="text-warning">Nenhum resíduo selecionado.</p>';
            return;
        }
        var html = '<ul class="mb-0">';
        lines.forEach(function (it) {
            html += '<li>' + escapeHtml(it.tipo_nome) + ' — franquia ' + formatMoney(it.saldo_incluso)
                + ' ' + escapeHtml(it.unidade) + ', excedente R$ ' + formatMoney(it.valor_excedente) + '</li>';
        });
        html += '</ul>';
        self.revisaoItens.innerHTML = html;
    };

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    window.initContratoWizard = function (opts) {
        var w = new ContratoWizard(opts);
        if (w.planoSelect) {
            w.planoSelect.addEventListener('change', function () {
                w.loadPlano(w.planoSelect.value, null);
            });
            if (w.planoSelect.value) {
                w.loadPlano(w.planoSelect.value, w.prefillIds);
            }
        }
        if (w.valorInput) {
            w.valorInput.addEventListener('input', function () {
                w.valorTouched = true;
            });
        }
        if (w.qtdMesesInput) {
            w.qtdMesesInput.addEventListener('input', function () {
                w.qtdMesesInput.dataset.userSet = '1';
            });
        }
        var form = qs(opts.form);
        if (form) {
            form.addEventListener('submit', function (e) {
                if (!w.validateItens() || !w.validateComercial()) {
                    e.preventDefault();
                }
            });
        }
        return w;
    };

    window.initContratoItensEditor = function (opts) {
        var prefill = Array.isArray(opts.prefillRaw) ? opts.prefillRaw : parsePrefill(opts.prefillRaw);
        var w = new ContratoWizard({
            url: opts.url,
            prefillIds: prefill,
            planoSelect: null,
            itensHost: qs(opts.itensHost),
            modeloInfo: null,
            revisaoItens: null,
            revisaoMensal: null,
            valorInput: null,
            qtdMesesInput: null,
            form: opts.form || '#form_contrato_editar'
        });
        w.loadPlano(opts.planoId, prefill);
        return w;
    };
})();
