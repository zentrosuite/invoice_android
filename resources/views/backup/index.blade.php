@extends('layouts.app', ['pageTitle' => 'Backup'])
@section('title', 'Backup')

@section('content')
    <div x-data="backupPage(@js([
        'create' => route('backup.create'),
        'saved' => route('backup.saved'),
        'restore' => route('backup.restore'),
    ]))">
        <div @class(['alert d-flex gap-2 align-items-start', 'alert-warning' => $overdue, 'alert-success' => ! $overdue])>
            <i class="{{ $overdue ? 'ri-error-warning-line' : 'ri-shield-check-line' }} fs-18"></i>
            <div>
                <div class="fw-medium">
                    @if ($lastSaved)
                        Last backup saved {{ $lastSaved->diffForHumans() }} ({{ $lastSaved->format('d M Y, h:i A') }})
                    @else
                        No backup saved yet
                    @endif
                </div>
                <div class="fs-13">
                    Your {{ $invoices }} {{ Str::plural('invoice', $invoices) }} and {{ $customers }} {{ Str::plural('customer', $customers) }}
                    live only on this phone. If it is lost or reset, or the app is uninstalled, they go with it —
                    unless a backup is kept somewhere else.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Back up</h5></div>
            <div class="card-body">
                <p class="text-muted fs-13">
                    One file with all your invoices, customers, payments, settings, logo and signature.
                    Keep it in Google Drive, or send it to yourself on WhatsApp.
                </p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary btn-lg" @click="save()" :disabled="busy">
                        <span x-show="busy === 'save'" class="spinner-border spinner-border-sm me-1"></span>
                        <i class="ri-save-3-line me-1" x-show="busy !== 'save'"></i>Save backup (Drive, Downloads…)
                    </button>
                    <button type="button" class="btn btn-soft-success" @click="share()" :disabled="busy">
                        <span x-show="busy === 'share'" class="spinner-border spinner-border-sm me-1"></span>
                        <i class="ri-share-forward-line me-1" x-show="busy !== 'share'"></i>Share backup (WhatsApp, email…)
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Restore</h5></div>
            <div class="card-body">
                <p class="text-muted fs-13">
                    Use this on a new phone, or after reinstalling the app. It <strong>replaces everything</strong>
                    on this phone with the backup, and the PIN becomes the one from the backup.
                </p>
                <button type="button" class="btn btn-outline-danger w-100" @click="restore()" :disabled="busy">
                    <span x-show="busy === 'restore'" class="spinner-border spinner-border-sm me-1"></span>
                    <i class="ri-history-line me-1" x-show="busy !== 'restore'"></i>Restore from a backup file
                </button>
            </div>
        </div>

        <div class="text-success fs-13" x-show="note" x-text="note" x-cloak></div>
        <div class="text-danger fs-13" x-show="error" x-text="error" x-cloak></div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('backupPage', (urls) => ({
                busy: null,
                note: '',
                error: '',
                start(kind) { this.busy = kind; this.note = ''; this.error = ''; },
                fail(e) { this.error = e.message || 'Something went wrong.'; },
                save() {
                    this.start('save');
                    ZentroNative.post(urls.create)
                        .then((f) => ZentroNative.saveFile(f.path, f.name, f.mime))
                        .then((saved) => {
                            if (!saved) { return; }
                            return ZentroNative.post(urls.saved).then(() => { location.reload(); });
                        })
                        .catch((e) => this.fail(e))
                        .finally(() => { this.busy = null; });
                },
                share() {
                    this.start('share');
                    ZentroNative.post(urls.create)
                        .then((f) => ZentroNative.shareFile(f.path, 'Zentro Invoice backup', f.name))
                        // The share sheet cannot say whether it was sent; counted as kept once offered.
                        .then(() => ZentroNative.post(urls.saved))
                        .then(() => { this.note = 'Backup handed to the share sheet.'; })
                        .catch((e) => this.fail(e))
                        .finally(() => { this.busy = null; });
                },
                restore() {
                    if (!confirm('Replace everything on this phone with the backup? This cannot be undone.')) { return; }
                    this.start('restore');
                    ZentroNative.pickFile(['*/*'])
                        .then((file) => file ? ZentroNative.post(urls.restore, { path: file.path }) : null)
                        .then((result) => { if (result && result.redirect) { location.href = result.redirect; } })
                        .catch((e) => this.fail(e))
                        .finally(() => { this.busy = null; });
                },
            }));
        });
    </script>
@endpush
