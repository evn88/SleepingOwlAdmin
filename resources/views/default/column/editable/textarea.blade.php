<a href="#"
    {!! $attributes !!}
    data-name="{{ $name }}"
    data-value="{{ $value }}"
    data-url="{{ $url }}"
    data-type="textarea"
    data-pk="{{ $id }}"
    data-mode="{{ $mode }}"
    data-disabled="{{ !$isEditable }}"
>{{ $value }}</a>

{!! $append !!}
