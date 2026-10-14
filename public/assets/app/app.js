/*
 | Application-wide behaviour. Plain script, no bundler — this app is deployed to hosting that
 | cannot run one. Alpine components are registered on `alpine:init`, which fires before Alpine
 | (loaded with `defer` after this file) starts.
 */
(function () {
    'use strict';

    /*
     | A thin bar across the top while the next page is on its way: after a tap on a link or a
     | row, and while a form is saving. It creeps towards the end and never finishes on its own;
     | the next page simply replaces it. Back from the page cache (pageshow) clears it.
     */
    var bar = document.createElement('div');
    bar.className = 'zi-loadbar';
    bar.setAttribute('aria-hidden', 'true');
    document.body.appendChild(bar);

    var ZentroLoader = window.ZentroLoader = {
        start: function () {
            bar.classList.remove('is-done');
            // Restart the animation even if it is already running.
            bar.classList.remove('is-loading');
            void bar.offsetWidth;
            bar.classList.add('is-loading');
        },
        done: function () {
            if (!bar.classList.contains('is-loading')) { return; }
            bar.classList.add('is-done');
            setTimeout(function () { bar.classList.remove('is-loading', 'is-done'); }, 300);
        },
    };

    window.addEventListener('pageshow', function () { ZentroLoader.done(); });

    document.addEventListener('click', function (event) {
        var link = event.target.closest && event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey) { return; }
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || link.target === '_blank' || link.hasAttribute('download')) { return; }
        try {
            var url = new URL(link.href, location.href);
            if (url.origin !== location.origin || (url.pathname === location.pathname && url.search === location.search && url.hash)) { return; }
        } catch (e) { return; }
        ZentroLoader.start();
    });

    /*
     | Every POST form is sent with fetch instead of as a native form post.
     |
     | Inside the Android app a native post goes through NativePHP's body capture, which can lose
     | the form's fields on the way to PHP, and NativePHP then follows the redirect itself, which
     | leaves the page on the form's URL. fetch is the path the rest of the app already uses on
     | the phone. FormRedirectsAsJson answers a redirect with {"redirect": url}, and the page goes
     | there — flash messages and validation errors arrive exactly as after a normal redirect.
     |
     | Runs in the bubble phase on document: a form's own handler (the invoice builder fills in a
     | hidden field) and the data-confirm prompt above have already had their turn.
     | <form data-native-submit> opts out.
     */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) { return; }

        var submitter = event.submitter || null;
        var method = (submitter && submitter.getAttribute('formmethod')) || form.getAttribute('method') || 'get';
        if (method.toLowerCase() !== 'post' || form.hasAttribute('data-native-submit')) {
            ZentroLoader.start();
            return;
        }

        var action = (submitter && submitter.getAttribute('formaction')) || form.action;
        try {
            if (new URL(action, location.href).origin !== location.origin) { return; }
        } catch (e) { return; }

        event.preventDefault();
        if (form.dataset.sending === '1') { return; }
        form.dataset.sending = '1';

        var data;
        try {
            data = new FormData(form, submitter || undefined);
        } catch (e) {
            data = new FormData(form);
            if (submitter && submitter.name) { data.append(submitter.name, submitter.value); }
        }

        var buttons = form.querySelectorAll('button[type=submit], button:not([type])');
        buttons.forEach(function (b) { b.disabled = true; });
        ZentroLoader.start();

        var failed = function () {
            form.dataset.sending = '';
            buttons.forEach(function (b) { b.disabled = false; });
            ZentroLoader.done();
            var message = 'That could not be saved. Please try again.';
            if (window.Swal) {
                window.Swal.fire({ icon: 'error', text: message, customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
            } else {
                window.alert(message);
            }
        };

        fetch(action, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'X-Zentro-Form': '1', 'Accept': 'text/html, application/json' },
        }).then(function (response) {
            var type = response.headers.get('Content-Type') || '';
            if (type.indexOf('application/json') !== -1) {
                return response.json().then(function (body) {
                    if (!body || !body.redirect) { throw new Error('No redirect'); }
                    location.assign(body.redirect);
                });
            }
            // Anything else (an error page, a session that expired) is shown as the page.
            return response.text().then(function (html) {
                document.open();
                document.write(html);
                document.close();
            });
        }).catch(failed);
    }, false);

    /*
     | Dates read dd/mm/yyyy whatever the phone's language. A date field shows the phone's own
     | format (10/14/2026 on an English-US phone) and cannot be told otherwise, so it is laid,
     | invisible, over a box that shows the date as dd/mm/yyyy: a tap still opens the phone's
     | calendar, and the form still sends yyyy-mm-dd.
     */
    function showDate(input, box) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(input.value || '');
        box.textContent = m ? m[3] + '/' + m[2] + '/' + m[1] : 'dd/mm/yyyy';
        box.classList.toggle('is-empty', !m);
    }

    document.querySelectorAll('input[type=date]').forEach(function (input) {
        var wrap = document.createElement('div');
        wrap.className = 'zi-date';
        var box = document.createElement('div');
        box.className = 'form-control zi-date-text';
        box.setAttribute('aria-hidden', 'true');
        if (input.classList.contains('is-invalid')) {
            wrap.classList.add('is-invalid');
            box.classList.add('is-invalid');
        }
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(box);
        wrap.appendChild(input);
        input.classList.add('zi-date-input');
        showDate(input, box);
        input.addEventListener('change', function () { showDate(input, box); });
        input.addEventListener('input', function () { showDate(input, box); });
        // Where a tap on the invisible field does not open the calendar by itself.
        input.addEventListener('click', function () {
            try { if (input.showPicker) { input.showPicker(); } } catch (e) {}
        });
    });

    /*
     | Pull to refresh. From the top of a page, drag down: a round arrow follows the finger and
     | turns; let go past the mark and the page reloads. Only on the app's own pages (the top
     | bar is there), and never while typing, with a dialog or the menu open, or on a page that
     | asks not to (<form data-no-refresh>: the invoice builder, where a stray pull would lose an
     | unsaved invoice).
     */
    (function pullToRefresh() {
        if (!document.getElementById('page-topbar') || !('ontouchstart' in window)) { return; }

        var MAX = 110;        // furthest the arrow travels, px
        var TRIGGER = 72;     // let go beyond this to refresh
        var spinner = document.createElement('div');
        spinner.className = 'zi-ptr';
        spinner.setAttribute('aria-hidden', 'true');
        spinner.innerHTML = '<i class="ri-refresh-line"></i>';
        document.body.appendChild(spinner);

        var startY = null;
        var pull = 0;
        var busy = false;

        function blocked(target) {
            if (busy || window.scrollY > 0) { return true; }
            if (document.querySelector('[data-no-refresh]')) { return true; }
            if (document.body.classList.contains('vertical-sidebar-enable')) { return true; }
            if (document.querySelector('.zi-modal:not([style*="display: none"]), .swal2-container')) { return true; }
            var active = document.activeElement;
            if (active && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName)) { return true; }
            // Inside something that scrolls on its own and is not at its top.
            for (var el = target; el && el !== document.body; el = el.parentElement) {
                if (el.scrollTop > 0) { return true; }
            }
            return false;
        }

        function show(distance) {
            var travel = Math.min(MAX, distance);
            spinner.style.transform = 'translate(-50%, ' + travel + 'px) rotate(' + (travel * 3) + 'deg)';
            spinner.style.opacity = String(Math.min(1, travel / TRIGGER));
            spinner.classList.toggle('is-ready', travel >= TRIGGER);
        }

        function reset() {
            spinner.classList.add('is-returning');
            spinner.style.transform = '';
            spinner.style.opacity = '';
            spinner.classList.remove('is-ready');
            setTimeout(function () { spinner.classList.remove('is-returning'); }, 250);
        }

        document.addEventListener('touchstart', function (event) {
            startY = event.touches.length === 1 && !blocked(event.target) ? event.touches[0].clientY : null;
            pull = 0;
        }, { passive: true });

        document.addEventListener('touchmove', function (event) {
            if (startY === null) { return; }
            var dy = event.touches[0].clientY - startY;
            if (dy <= 0 || window.scrollY > 0) { pull = 0; show(0); return; }
            // Resistance: the arrow moves less than the finger.
            pull = dy * 0.5;
            show(pull);
        }, { passive: true });

        document.addEventListener('touchend', function () {
            if (startY === null) { return; }
            startY = null;
            if (pull >= TRIGGER) {
                busy = true;
                spinner.classList.add('is-refreshing');
                spinner.style.transform = 'translate(-50%, ' + TRIGGER + 'px)';
                ZentroLoader.start();
                location.reload();
            } else {
                reset();
            }
            pull = 0;
        }, { passive: true });
    })();

    /*
     | Inside the Android app: status bar icons dark on a light page and light on a dark one.
     | Android would otherwise follow the PHONE's dark mode, not this app's own switch.
     */
    if (window.AndroidPOST && window.ZentroNative) {
        var syncBars = function () {
            var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            window.ZentroNative.call('ZentroSystemBars.SetStyle', { dark: dark }).catch(function () {});
        };
        syncBars();
        new MutationObserver(syncBars).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
    }

    /*
     | Clickable table rows: <tr data-href="…">. A click on a link, button or form control inside
     | the row does its own thing; anywhere else opens the row's record. Ctrl/Cmd-click opens a
     | new tab, as a link would. The row's main link stays focusable for keyboard users.
     */
    document.addEventListener('click', function (event) {
        var row = event.target.closest && event.target.closest('tr[data-href]');
        if (!row || event.target.closest('a, button, input, select, textarea, label')) {
            return;
        }
        if (event.ctrlKey || event.metaKey) {
            window.open(row.dataset.href, '_blank');
        } else {
            ZentroLoader.start();
            window.location.href = row.dataset.href;
        }
    });

    /*
     | Confirmation for destructive actions: <form data-confirm="Delete this invoice?">.
     | SweetAlert when present, the browser's confirm() otherwise.
     */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();

        var proceed = function () {
            form.dataset.confirmed = '1';
            form.requestSubmit ? form.requestSubmit(event.submitter || undefined) : form.submit();
        };

        if (window.Swal) {
            window.Swal.fire({
                title: form.dataset.confirm,
                text: form.dataset.confirmText || '',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmButton || 'Yes, continue',
                customClass: { confirmButton: 'btn btn-danger w-xs me-2', cancelButton: 'btn btn-light w-xs' },
                buttonsStyling: false,
            }).then(function (result) {
                if (result.isConfirmed) { proceed(); }
            });
        } else if (window.confirm(form.dataset.confirm)) {
            proceed();
        }
    }, true);
})();
