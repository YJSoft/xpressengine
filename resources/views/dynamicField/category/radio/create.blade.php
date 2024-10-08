<div class="xe-form-group xe-dynamicField">
    <label class="__xe_df __xe_df_category __xe_df_category_{{$config->get('id')}}">
        {{xe_trans($config->get('label'))}}

        @if (!$config->get('use', false))
            @include('dynamicField.userActivateLink')
        @endif
    </label>

    @if ($config->get('skinDescription') !== '')<small>{{$config->get('skinDescription')}}</small>@endif

    @foreach ($data['items'] as $item)
        <label class="xe-label">
            <input
                type="radio"
                name="{{$config->get('id') . '_item_id'}}"
                value="{{$item['value']}}"
                @if (!$config->get('use', false)) disabled @endif
            >
            <span class="xe-input-helper"></span>
            <span class="xe-label-text">{{xe_trans($item['text'])}}</span>
        </label>
    @endforeach
</div>
