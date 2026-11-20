<div class="s4d-field">
    @if ($field->type === 'checkbox')
        <label class="s4d-switch">
            <input type="checkbox" name="{{ $field->key }}" @checked(filter_var($values[$field->key] ?? '', FILTER_VALIDATE_BOOLEAN))>
            <span class="track"></span>
            <span>
                <strong>{{ $field->label }}</strong>
                @if ($field->help)
                    <span class="s4d-note">{{ $field->help }}</span>
                @endif
            </span>
        </label>
    @elseif ($field->type === 'radio')
        <label>{{ $field->label }}</label>
        <div class="s4d-segment" role="radiogroup" aria-label="{{ $field->label }}">
            @foreach ($field->options as $value => $label)
                <label>
                    <input type="radio" name="{{ $field->key }}" value="{{ $value }}" @checked((string) ($values[$field->key] ?? $field->default) === (string) $value)>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @if ($field->help)
            <div class="s4d-note">{{ $field->help }}</div>
        @endif
    @else
        <label for="f-{{ $field->key }}">{{ $field->label }} @if ($field->required)<span class="req">*</span>@endif</label>
        <div class="s4d-input">
            <input id="f-{{ $field->key }}" type="{{ $field->type }}" name="{{ $field->key }}" value="{{ $values[$field->key] ?? '' }}" spellcheck="false" @required($field->required)
                   @if ($field->type === 'password') class="has-toggle" autocomplete="new-password" @endif>
            @if ($field->type === 'password')
                <button type="button" class="s4d-reveal" data-reveal="f-{{ $field->key }}">Show</button>
            @endif
        </div>
        @if ($field->help)
            <div class="s4d-note">{{ $field->help }}</div>
        @endif
    @endif
</div>
