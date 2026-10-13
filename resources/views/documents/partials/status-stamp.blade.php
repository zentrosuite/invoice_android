@if ($isPaid)
    <span class="stamp stamp-paid">PAID</span>
@elseif ($isCancelled)
    <span class="stamp stamp-cancelled">CANCELLED</span>
@elseif ($isPartial)
    <span class="stamp stamp-partial">PART PAID</span>
@elseif ($isDraft)
    <span class="stamp stamp-draft">DRAFT</span>
@endif
