{{ XeFrontend::title($title) }}

{!! compile($pageId, $content, true) !!}

@if(Auth::check() && in_array(Auth::user()->rating, ['super', 'manager']))
    <a class="btn btn-default" href="{!! route('manage.plugin.page.edit', $pageId) !!}">{{xe_trans('xe::goSettingPage')}}</a>
@endif

@if ($config->get('comment') === true)
{!! uio('comment', ['target' => $page]) !!}
@endif
