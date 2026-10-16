{{-- Invoices left half-made when the app was closed or crashed (AutosaveStore). Shown on the
     dashboard and the invoice list, the first places the owner lands after reopening the app. --}}
@foreach (app(\App\Services\Invoices\AutosaveStore::class)->unfinished() as $unsaved)
    <div class="alert alert-primary zi-unsaved d-flex flex-wrap align-items-center gap-2" role="status">
        <i class="ri-history-line fs-20"></i>
        <div class="me-auto min-w-0">
            <div class="fw-semibold">
                {{ $unsaved->invoiceId() === null ? 'Unfinished new invoice' : 'Unsaved changes to an invoice' }}
            </div>
            <div class="fs-13 text-truncate">{{ $unsaved->label }} · {{ $unsaved->updated_at->format('d/m/Y, g:i a') }}</div>
        </div>
        <form method="POST" action="{{ route('invoices.autosave.destroy') }}"
              data-confirm="Discard this unsaved invoice?" data-confirm-text="What was typed is lost." data-confirm-button="Discard">
            @csrf @method('DELETE')
            <input type="hidden" name="key" value="{{ $unsaved->key }}">
            <button class="btn btn-light btn-sm">Discard</button>
        </form>
        <a href="{{ route('invoices.autosave.restore', ['key' => $unsaved->key]) }}" class="btn btn-primary btn-sm">
            <i class="ri-play-line me-1"></i>Continue
        </a>
    </div>
@endforeach
