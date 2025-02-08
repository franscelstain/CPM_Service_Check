<?php

namespace App\Models\Investor\Financial\Plannig\Goal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class InvestmentDetail extends Model 
{    
    protected $table        = 't_goal_investment_detail';
    protected $primaryKey   = 'goal_invest_detail_id';
    protected $guarded      = ['goal_invest_detail_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        /*return [
            'product_id'       => 'required',
            'product_name'     => 'required|string',
            'asset_class_id'   => 'required',
            'asset_class_name' => 'required|string',
            'investment_type'  => 'required|string',
            'billing_date'     => 'nullable|numeric',
            'expected_return'  => 'required|numeric',
            'amount'           => 'required|numeric',
            'allocation'       => 'required|numeric',
            'sharpe_ratio'     => 'required|numeric',
            'treynor_ratio'    => 'required|numeric',
            'fee_product_id'   => 'required|numeric|exists:m_products_fee,fee_product_id',
            'fee_percentage'   => 'required|numeric',
            'fee_product'      => 'required|numeric',
            'payment_method_id'=> 'required',
        ];*/
        return [];
    }    
}