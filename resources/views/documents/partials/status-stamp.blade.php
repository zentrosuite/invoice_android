@if ($isPaid)
    <span class="stamp stamp-paid">PAID</span>
@elseif ($isCancelled)
    <span class="stamp stamp-cancelled">CANCELLED</span>
@elseif ($isDraft)
    <span class="stamp stamp-draft">DRAFT</span>
@endif
