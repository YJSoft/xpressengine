<div class="panel-group">
    <div class="panel">
        <div class="panel-heading">
            <div class="pull-left">
                <h3 class="panel-title">{{xe_trans('page::pageBasicSetting')}}</h3>
            </div>
        </div>
        <div class="panel-body">
        <div class="form-group">
        <label class="text-title">Comment</label>
        <select name="comment" class="form-control">
        <option value="true" {{($config->get('comment') == true) ? 'selected="selected"' : ''}} >{{xe_trans('xe::use')}}</option>
        <option value="false" {{($config->get('comment') == false) ? 'selected="selected"' : ''}}>{{xe_trans('xe::disuse')}}</option>
        </select>
        </div>
        <div class="form-group">
        <label class="text-title">Mobile Content</label>
        <select name="mobile" class="form-control">
        <option value="true" {{($config->get('mobile') == true) ? 'selected="selected"' : ''}} >{{xe_trans('xe::use')}}</option>
        <option value="false" {{($config->get('mobile') == false) ? 'selected="selected"' : ''}}>{{xe_trans('xe::disuse')}}</option>
        </select>
        </div>
        </div>
    </div>
</div>
