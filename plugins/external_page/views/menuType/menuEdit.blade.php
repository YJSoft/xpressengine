<div class="panel-group">
    <div class="panel">
        <div class="panel-heading">
            <div class="pull-left">
                <h3 class="panel-title">External Page Setting</h3>
            </div>
        </div>
        <div class="panel-body">
            <div class="form-group">
                <label>File Location</label>

                <dl>
                    <dd><input type="text" name="includePath" class="form-control"
                        value="{{Input::old('includePath', $config->get('includePath'))}}"
                        placeholder="포함시킬 PHP file의 경로를 지정해주세요({{ base_path() }}/ 이하의 경로)"/>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>
