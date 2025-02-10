<?php

namespace App\Models\SA\Assets\Portfolio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AllocationWeightDetail extends Model
{
    protected $table        = 'm_portfolio_allocations_weights_detail';
    protected $primaryKey   = 'allocation_weight_detail_id';
    protected $fillable     = ['allocation_weight_id', 'product_id', 'weight', 'created_by', 'created_host'];
    
    public static function rules($id = null)
    {
        return [
            //
        ];
    }
}
