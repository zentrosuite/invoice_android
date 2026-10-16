/*
 | The invoice builder (resources/views/invoices/builder.blade.php).
 |
 | Owns the line grid, the customer picker and the live totals. The totals here are for the
 | person typing; the server recomputes every figure from the submitted lines with exact decimal
 | arithmetic (App\Services\Invoices\InvoiceCalculator) and only those numbers are stored. Sums
 | here are done in paise, as integers, so the preview does not drift from what gets saved.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    var nextKey = 1;

    function blankLine() {
        return { key: nextKey++, description: '', details: '', showDetails: false, quantity: '', rate: '', inclusion: 'included' };
    }

    /** A typed amount ("1,25,000", "₹ 899", "") as a number, or 0. */
    function parse(value) {
        var n = parseFloat(String(value === null || value === undefined ? '' : value).replace(/[^0-9.\-]/g, ''));
        return isFinite(n) && n > 0 ? n : 0;
    }

    function toPaise(value) {
        return Math.round(parse(value) * 100);
    }

    window.Alpine.data('invoiceBuilder', function (state) {
        return {
            mode: state.mode,
            itemisedTemplate: state.itemisedTemplate,
            showQuantity: state.showQuantity,
            items: (state.items || []).map(function (item) {
                return Object.assign(blankLine(), item, { showDetails: !!item.details });
            }),
            packageTotal: state.packageTotal === null ? '' : String(state.packageTotal),
            discountType: state.discountType || 'amount',
            discountValue: state.discountValue === null ? '' : String(state.discountValue),
            taxPercent: state.taxPercent === null ? '' : String(state.taxPercent),
            currency: state.currency,
            customer: state.customer,
            walkin: state.walkin,
            walkinMode: !!state.walkinMode,
            callout: state.callout,
            urls: state.urls,

            // The customer's advance: adjust it, and how much. Untouched, the amount follows the
            // total (as much of the advance as the invoice can take).
            advance: {
                on: !!state.applyAdvanceOn,
                touched: state.applyAdvance !== null && state.applyAdvance !== undefined,
                amount: state.applyAdvance === null || state.applyAdvance === undefined ? '' : String(state.applyAdvance),
            },
            advanceAppliedBefore: toPaise(state.advanceApplied),
            amountPaidBefore: toPaise(state.amountPaid),

            dirty: false,
            submitting: false,
            autosaving: null,

            picker: { open: false, term: '', results: [], loading: false, active: 0, request: 0 },
            quick: { open: false, saving: false, failed: '', errors: {}, form: {} },

            init: function () {
                var self = this;

                if (this.items.length === 0) {
                    this.items.push(blankLine());
                }

                // Leaving with unsaved work asks first. Submitting the form is not "leaving".
                window.addEventListener('beforeunload', function (event) {
                    if (self.dirty && !self.submitting) {
                        event.preventDefault();
                        event.returnValue = '';
                    }
                });

                if (state.autosave) { this.startAutosave(state.autosave); }
            },

            /* ---- Auto-save ------------------------------------------------------------ */

            /** The form exactly as a submit would send it, URL-encoded. */
            serialize: function () {
                return new URLSearchParams(new FormData(this.$root)).toString();
            },

            /**
             * Unsaved work is written to the phone every few seconds, and at once when the app is
             * left (home button, another app, the screen turning off). If the app is then closed
             * or killed, the builder offers to continue from it (AutosaveController).
             */
            startAutosave: function (cfg) {
                var self = this;
                var last = null;
                var token = function () {
                    var meta = document.querySelector('meta[name="csrf-token"]');
                    return meta ? meta.content : '';
                };
                var send = function (leaving) {
                    if (!self.dirty || self.submitting) { return; }
                    var form = self.serialize();
                    if (form === last) { return; }
                    last = form;
                    var request = fetch(cfg.url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        keepalive: !!leaving,
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token() },
                        body: JSON.stringify({ _token: token(), key: cfg.key, form: form }),
                    }).then(function (r) { if (!r.ok) { last = null; } })
                        .catch(function () { last = null; });
                    self.autosaving = request;
                    request.finally(function () { if (self.autosaving === request) { self.autosaving = null; } });
                };
                setInterval(send, 3000);
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'hidden') { send(true); }
                });
                window.addEventListener('pagehide', function () { send(true); });
            },

            /**
             * The submit event fires before the browser collects the form data, so a value set
             * here is the one sent. Which button was pressed comes from event.submitter.
             */
            onSubmit: function (event) {
                // A background save still on its way could land after this save and bring the
                // finished invoice back as "unsaved work". Let it arrive first, then submit.
                if (this.autosaving) {
                    event.preventDefault();
                    var form = this.$root;
                    var submitter = event.submitter;
                    this.autosaving.finally(function () {
                        form.requestSubmit ? form.requestSubmit(submitter || undefined) : form.submit();
                    });
                    this.autosaving = null;
                    return;
                }
                var save = event.submitter && event.submitter.dataset ? event.submitter.dataset.save : null;
                if (save && this.$refs.saveAs) {
                    this.$refs.saveAs.value = save;
                }
                this.submitting = true;
            },

            /* ---- Lines ---------------------------------------------------------------- */

            addLine: function (afterIndex) {
                var line = blankLine();
                var at = afterIndex === undefined ? this.items.length : afterIndex + 1;
                this.items.splice(at, 0, line);
                this.dirty = true;

                // Focus the new description, so a list can be typed line after line with Enter.
                this.$nextTick(function () {
                    var inputs = this.$root.querySelectorAll('.zi-line .zi-col-desc input[type=text]');
                    if (inputs[at]) { inputs[at].focus(); }
                }.bind(this));
            },

            removeLine: function (index) {
                this.items.splice(index, 1);
                if (this.items.length === 0) {
                    this.items.push(blankLine());
                }
                this.dirty = true;
            },

            move: function (index, step) {
                var target = index + step;
                if (target < 0 || target >= this.items.length) { return; }
                var moved = this.items.splice(index, 1)[0];
                this.items.splice(target, 0, moved);
                this.dirty = true;
            },

            /* ---- Money (paise) -------------------------------------------------------- */

            linePaise: function (item) {
                if (!this.showQuantity) { return toPaise(item.rate); }
                var qty = String(item.quantity).trim() === '' ? 1 : parse(item.quantity);
                return Math.round(qty * toPaise(item.rate));
            },

            lineAmount: function (item) {
                return this.linePaise(item) / 100;
            },

            subtotalPaise: function () {
                if (this.mode === 'package') { return toPaise(this.packageTotal); }
                var self = this;
                return this.items.reduce(function (sum, item) {
                    return String(item.description).trim() === '' ? sum : sum + self.linePaise(item);
                }, 0);
            },

            discountPaise: function () {
                var sub = this.subtotalPaise();
                var value = parse(this.discountValue);
                var paise = this.discountType === 'percent'
                    ? Math.round(sub * Math.min(value, 100) / 100)
                    : Math.round(value * 100);
                return Math.min(paise, sub);
            },

            taxPaise: function () {
                return Math.round((this.subtotalPaise() - this.discountPaise()) * Math.min(parse(this.taxPercent), 100) / 100);
            },

            subtotal: function () { return this.subtotalPaise() / 100; },
            discount: function () { return this.discountPaise() / 100; },
            tax: function () { return this.taxPaise() / 100; },
            total: function () { return (this.subtotalPaise() - this.discountPaise() + this.taxPaise()) / 100; },

            /* ---- Advance (paise) ------------------------------------------------------ */

            advanceAppliedPaise: function () { return this.advanceAppliedBefore; },

            /** The customer's advance not yet used anywhere. */
            advanceAvailablePaise: function () {
                return this.customer && this.customer.advance ? toPaise(this.customer.advance) : 0;
            },

            /** The most this save can adjust: the advance, or what the invoice still owes. */
            advanceMaxPaise: function () {
                var owed = this.subtotalPaise() - this.discountPaise() + this.taxPaise() - this.amountPaidBefore;
                return Math.max(0, Math.min(this.advanceAvailablePaise(), owed));
            },

            advancePaise: function () {
                if (!this.advance.on || this.advanceAvailablePaise() <= 0) { return 0; }
                return this.advance.touched ? Math.min(toPaise(this.advance.amount), this.advanceMaxPaise()) : this.advanceMaxPaise();
            },

            advanceTooMuch: function () {
                return this.advance.on && this.advance.touched && toPaise(this.advance.amount) > this.advanceMaxPaise();
            },

            /** What the amount box shows: the typed amount, or the suggestion while untouched. */
            advanceText: function () {
                if (this.advance.touched) { return this.advance.amount; }
                var paise = this.advanceMaxPaise();
                return paise > 0 ? String(paise % 100 === 0 ? paise / 100 : (paise / 100).toFixed(2)) : '';
            },

            balanceAfterAdvancePaise: function () {
                var total = this.subtotalPaise() - this.discountPaise() + this.taxPaise();
                return Math.max(0, total - this.amountPaidBefore - this.advancePaise());
            },

            money: function (value) {
                return this.currency + Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            /* ---- Customer picker ------------------------------------------------------ */

            openPicker: function () {
                this.picker.open = true;
                if (this.picker.results.length === 0) { this.search(); }
            },

            search: function () {
                var self = this;
                var ticket = ++this.picker.request;
                this.picker.loading = true;
                this.picker.open = true;

                fetch(this.urls.search + '?q=' + encodeURIComponent(this.picker.term), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (r) { return r.ok ? r.json() : []; })
                    .then(function (rows) {
                        // Out-of-order replies: only the latest search may fill the list.
                        if (ticket !== self.picker.request) { return; }
                        self.picker.results = rows;
                        self.picker.active = 0;
                    })
                    .catch(function () { /* network blip: keep what is shown */ })
                    .finally(function () {
                        if (ticket === self.picker.request) { self.picker.loading = false; }
                    });
            },

            movePicker: function (step) {
                var n = this.picker.results.length;
                if (n === 0) { return; }
                this.picker.active = (this.picker.active + step + n) % n;
            },

            choosePicker: function () {
                var row = this.picker.results[this.picker.active];
                if (row) { this.selectCustomer(row); }
            },

            selectCustomer: function (row) {
                this.customer = row;
                this.walkinMode = false;
                this.picker.open = false;
                this.picker.term = '';
                this.dirty = true;
            },

            clearCustomer: function () {
                this.customer = null;
                this.dirty = true;
                this.$nextTick(function () { this.$refs.customerSearch && this.$refs.customerSearch.focus(); }.bind(this));
            },

            /* ---- Quick-add customer --------------------------------------------------- */

            openQuick: function () {
                var term = this.picker.term.trim();
                var looksLikePhone = /^[0-9+\-\s()]{6,}$/.test(term);
                this.quick.form = {
                    name: looksLikePhone ? '' : term,
                    business_name: '',
                    phone: looksLikePhone ? term : '',
                    email: '', address_line1: '', city: '', state: '',
                };
                this.quick.errors = {};
                this.quick.failed = '';
                this.quick.open = true;
                this.picker.open = false;
                this.$nextTick(function () { this.$refs.quickName && this.$refs.quickName.focus(); }.bind(this));
            },

            saveQuick: function () {
                var self = this;
                var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                this.quick.saving = true;
                this.quick.errors = {};
                this.quick.failed = '';

                fetch(this.urls.quick, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    // The token goes in the body too: inside the Android app the header can be
                    // replaced by a stale one (see native.js), and Laravel reads the body first.
                    body: JSON.stringify(Object.assign({ _token: token }, this.quick.form)),
                })
                    .then(function (r) {
                        return r.json().then(function (body) { return { status: r.status, body: body }; });
                    })
                    .then(function (res) {
                        if (res.status === 201) {
                            self.selectCustomer(res.body);
                            self.picker.results = [];
                            self.quick.open = false;
                        } else if (res.status === 422) {
                            Object.keys(res.body.errors || {}).forEach(function (field) {
                                self.quick.errors[field] = res.body.errors[field][0];
                            });
                        } else {
                            self.quick.failed = 'Could not save the customer. Please try again.';
                        }
                    })
                    .catch(function () { self.quick.failed = 'No connection. Please try again.'; })
                    .finally(function () { self.quick.saving = false; });
            },
        };
    });
});
