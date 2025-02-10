<?php

namespace App\Models\SA\Assets\Portfolio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AllocationWeight extends Model
{
    protected $table        = 'm_portfolio_allocations_weights';
    protected $primaryKey   = 'allocation_weight_id';
    protected $fillable     = ['model_id', 'effective_date', 'expected_return_year', 'expected_return_month', 'volatility', 'sharpe_ratio', 'treynor_ratio','sortino_ratio', 'jensen_alpha', 'capm', 'roy_safety_ratio', 'aum', 'created_by', 'created_host'];
    
    public static function rules($id = null)
    {
        return [
            'model_id'              => ['required', Rule::unique('m_portfolio_allocations_weights')->ignore($id, 'allocation_weight_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                    })],
            'effective_date'        => 'required|date_format:Y-m-d', 
            'expected_return_year'  => 'required',
            'product_id'            => 'required|array',
            'weight'                => 'required|array',
        ];
    }
}
