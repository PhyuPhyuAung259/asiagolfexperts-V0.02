<div class="row">
    <div class="col-sm-4">
        <h3 class="form-group-title">{{__('Config agent')}}</h3>
        <p class="form-group-desc">{{__('Change your config agent system')}}</p>
    </div>
    <div class="col-sm-8">
        <div class="panel">
            <div class="panel-body">
                @if(is_default_lang())
                    <div class="form-group">
                        <label>{{__('Agent Commission Type')}}</label>
                        <div class="form-controls">
                            <select name="agent_commission_type" class="form-control">
                                <option value="percent" {{setting_item('agent_commission_type') == 'percent' ? 'selected' : ''  }}>{{__('Percent')}}</option>
                                <option value="amount" {{setting_item('agent_commission_type') == 'amount' ? 'selected' : ''  }}>{{__('Amount')}}</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>{{__('Agent commission value')}}</label>
                        <div class="form-controls">
                            <input type="text" class="form-control" name="agent_commission_amount" value="{{setting_item('agent_commission_amount',0) }}">
                        </div>
                        <p>
                            <i>{{__('Example value : 10 or 10.5')}}</i><br>
                        </p>
                    </div>
                @else
                    <p>{{__('You can edit on main lang.')}}</p>
                @endif
            </div>
        </div>
    </div>
</div>
