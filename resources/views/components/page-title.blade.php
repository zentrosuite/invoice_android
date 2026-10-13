{{--
    Converted from Velzon's partials/page-title.html.

    The theme hard-coded a two-level breadcrumb. This takes an ordered
    label => url map so deeper trails work, with the final entry rendered as the
    active (unlinked) crumb:

        @section('content') ... @endsection
        with, in the controller-facing view:
            $pageTitle   = 'Edit product'
            $breadcrumbs = ['Inventory' => null, 'Products' => route(...)]

    A null url renders as plain text — useful for grouping labels that have no
    landing page of their own.

    @props are not used because this is @include'd from the layout rather than
    invoked as a component, so the variables arrive already scoped.
--}}
@php
    /** @var string $pageTitle */
    /** @var array<string, string|null> $breadcrumbs */
    $breadcrumbs = $breadcrumbs ?? [];
@endphp

<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">{{ $pageTitle }}</h4>

            @if ($breadcrumbs !== [])
                <div class="page-title-right">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb m-0">
                            @foreach ($breadcrumbs as $label => $url)
                                @if ($url === null)
                                    <li class="breadcrumb-item">{{ $label }}</li>
                                @else
                                    <li class="breadcrumb-item">
                                        <a href="{{ $url }}">{{ $label }}</a>
                                    </li>
                                @endif
                            @endforeach

                            <li class="breadcrumb-item active" aria-current="page">{{ $pageTitle }}</li>
                        </ol>
                    </nav>
                </div>
            @endif
        </div>
    </div>
</div>
