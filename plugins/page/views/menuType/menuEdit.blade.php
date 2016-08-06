<div class="panel-group">
    <div class="panel">
        <div class="panel-heading">
            <div class="pull-left">
                <h3 class="panel-title">{{xe_trans('page::pageBasicSetting')}}</h3>
            </div>
        </div>

        <div id="collapse-page-options" class="panel-collapse collapse in">
            <div class="panel-body">
                <div class="form-group">
                    <label for="page-option-comment">Comment</label>

                    <select id="page-option-comment" name="comment" class="form-control">
                        <option value="true" {{($config->get('comment') == true) ? 'selected="selected"' : ''}} >{{xe_trans('xe::use')}}</option>
                        <option value="false" {{($config->get('comment') == false) ? 'selected="selected"' : ''}}>{{xe_trans('xe::disuse')}}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="page-option-mobile">Mobile Content</label>

                    <select id="page-option-mobile" name="mobile" class="form-control">
                        <option value="true" {{($config->get('mobile') == true) ? 'selected="selected"' : ''}} >{{xe_trans('xe::use')}}</option>
                        <option value="false" {{($config->get('mobile') == false) ? 'selected="selected"' : ''}}>{{xe_trans('xe::disuse')}}</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="hidden" name="pageUid" value="{{$config->get('pageUid')}}"/>
