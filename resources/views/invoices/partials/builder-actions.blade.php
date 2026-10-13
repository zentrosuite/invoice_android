{{-- Save buttons. Included twice by the builder: once per layout. --}}
<div class="zi-form-actions zi-builder-actions">
    <div class="zi-mobile-total d-md-none">
        <span class="fs-12 text-muted d-block">Total</span>
        <span class="fw-semibold" x-text="money(total())"></span>
    </div>
    @if ($editing)
        <button type="submit" class="btn btn-primary px-4" :disabled="submitting">
            <i class="ri-save-3-line me-1"></i>Save changes
        </button>
    @else
        <button type="submit" class="btn btn-light" :disabled="submitting" data-save="draft">
            Save draft
        </button>
        <button type="submit" class="btn btn-primary" :disabled="submitting" data-save="sent">
            <i class="ri-check-line me-1"></i>Save invoice
        </button>
    @endif
</div>
