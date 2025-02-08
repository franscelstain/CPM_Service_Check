<?php

namespace App\Models\Investor\Financial\Plannig\Goal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Investment extends Model 
{    
    protected $table        = 't_goal_investment';
    protected $primaryKey   = 'goal_invest_id';
    protected $guarded      = ['goal_invest_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        return [
            'goal_id'               => 'required',
            'profile_id'            => 'required',
            'model_id'              => 'required',
            'goal_title'            => 'required|string',
            'today_amount'          => 'required|numeric',
            'time_horizon'          => 'required|numeric',
            'investment_amount'     => 'required|numeric',
            'projected_amount'      => 'required|numeric',
            'total_return'          => 'required|numeric',
            'future_amount'         => 'required|numeric',
            'first_investment'      => 'required|numeric',
            'monthly_investment'    => 'required|numeric',
        ];
    }
}