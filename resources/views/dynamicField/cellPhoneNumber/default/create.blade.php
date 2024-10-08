<div class="xe-form-group xe-dynamicField">
    <label class="xu-form-group__label __xe_df __xe_df_number __xe_df_cell_phone_number_{{$config->get('id')}}">
        {{xe_trans($config->get('label'))}}

        @if (!$config->get('use', false))
            @include('dynamicField.userActivateLink')
        @endif
    </label>

    @if ($config->get('skinDescription') !== '')<small>{{$config->get('skinDescription')}}</small>@endif

    <input
        type="text"
        name="{{$key['cell_phone_number']}}"
        class="xe-form-control xu-form-group__control __xe_df __xe_df_cell_phone_number __xe_df_cell_phone_number_{{$config->get('id')}}"
        value="{{Request::old($key['cell_phone_number'])}}"
        data-valid-name="{{ xe_trans($config->get('label')) }}"
        placeholder="{{xe_trans($config->get('placeholder', ''))}}"
        @if (!$config->get('use', false)) disabled @endif
    />
</div>
