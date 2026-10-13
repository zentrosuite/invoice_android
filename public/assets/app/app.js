/*
 | Application-wide behaviour. Plain script, no bundler — this app is deployed to hosting that
 | cannot run one. Alpine components are registered on `alpine:init`, which fires before Alpine
 | (loaded with `defer` after this file) starts.
 */
(function () {
    'use strict';

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
