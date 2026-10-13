/*
 | Application-wide behaviour. Plain script, no bundler — this app is deployed to hosting that
 | cannot run one. Alpine components are registered on `alpine:init`, which fires before Alpine
 | (loaded with `defer` after this file) starts.
 */
(function () {
    'use strict';

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
