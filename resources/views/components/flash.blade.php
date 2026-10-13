{{--
    Session flash messages, rendered as Bootstrap dismissible alerts.

    Services throw domain exceptions and controllers translate them into
    ->with('error', …); success paths use ->with('success', …). Validation errors
    are NOT shown here — they belong inline against their field, which is where
    the form components put them.
--}}
@php
    /*
     * `dismissAfter` is milliseconds, or null to stay until somebody closes it.
     *
     * Success and info go on their own: they confirm something the person just did and has
     * already seen happen, so leaving them on screen means every page slowly accumulates
     * receipts for work that is finished.
     *
     * Errors and warnings stay. They are the ones somebody needs to read, quite possibly after
     * looking away at the thing that went wrong, and a message that removes itself before it is
     * read is worse than no message — the operator only learns that something failed.
     */
    $levels = [
        'success' => ['class' => 'alert-success', 'icon' => 'ri-checkbox-circle-line', 'dismissAfter' => 4000],
        'error' => ['class' => 'alert-danger', 'icon' => 'ri-error-warning-line', 'dismissAfter' => null],
        'warning' => ['class' => 'alert-warning', 'icon' => 'ri-alert-line', 'dismissAfter' => null],
        'info' => ['class' => 'alert-info', 'icon' => 'ri-information-line', 'dismissAfter' => 4000],
    ];
@endphp

@foreach ($levels as $key => $level)
    @if (session()->has($key))
        <div class="alert {{ $level['class'] }} alert-dismissible alert-label-icon rounded-label fade show"
             role="alert"
             @if ($level['dismissAfter'])
                 {{-- Bootstrap's own close(), not $el.remove(): close() runs the fade transition
                      and fires the events any other listener expects, where removing the node
                      makes it vanish mid-frame. The timer is cleared on hover so a message is
                      never pulled out from under somebody reading it. --}}
                 x-data="{
                     timer: null,
                     start() {
                         this.timer = setTimeout(
                             () => bootstrap.Alert.getOrCreateInstance(this.$el).close(),
                             {{ (int) $level['dismissAfter'] }},
                         );
                     },
                     stop() { clearTimeout(this.timer); },
                 }"
                 x-init="start()"
                 @mouseenter="stop()"
                 @mouseleave="start()"
             @endif>
            <i class="{{ $level['icon'] }} label-icon"></i>
            {{ session($key) }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach

{{-- A single summary line when a form failed, pointing at the inline errors. --}}
@if ($errors->any() && ! session()->has('error'))
    <div class="alert alert-danger alert-dismissible alert-label-icon rounded-label fade show" role="alert">
        <i class="ri-error-warning-line label-icon"></i>
        Please correct the {{ $errors->count() }} highlighted
        {{ Str::plural('field', $errors->count()) }} and try again.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
