@props(['name' => 'pin', 'label' => 'PIN', 'autofocus' => false, 'autosubmit' => false, 'bag' => 'default'])
{{--
    A 4-digit PIN field: one real input (numeric keypad, masked), shown as four boxes that fill as
    digits are typed. With `autosubmit`, the form submits itself on the fourth digit — the unlock
    screen needs no button press.
--}}
<div class="zi-pin-field" x-data="{ v: '' }">
    <label class="form-label d-block text-center" for="{{ $name }}">{{ $label }}</label>
    <div class="zi-pin-wrap">
    <div class="zi-pin-boxes">
        <template x-for="i in 4" :key="i">
            <span class="zi-pin-box" :class="{ filled: v.length >= i, current: v.length === i - 1 }">
                <span x-show="v.length >= i">&bull;</span>
            </span>
        </template>
    </div>
    <input type="password" id="{{ $name }}" name="{{ $name }}" x-ref="input" x-model="v"
           inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="off" required
           @if ($autofocus) autofocus @endif
           @input="v = v.replace(/\D/g, '').slice(0, 4); @if ($autosubmit) if (v.length === 4) { $nextTick(() => $el.form.requestSubmit()); } @endif"
           class="zi-pin-input" aria-label="{{ $label }}">
    </div>
    @error($name, $bag)<div class="text-danger text-center fs-13 mt-2">{{ $message }}</div>@enderror
</div>
