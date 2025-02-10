<?php

namespace App\Models\Financial\Planning\Portfolio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Investment extends Model 
{    
    protected $table        = 't_portfolio_investment';
    protected $primaryKey   = 'investment_id';
    protected $guarded      = ['investment_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        return [
            'profile_id'            => 'required',
            'model_id'              => 'required',
            'investment_date'       => 'required|string',
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