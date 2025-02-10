<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Score extends Model
{
    protected $table        = 't_products_scores';
    protected $primaryKey   = 'product_score_id';
    protected $guarded      = ['product_score_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules($id = null)
    {
        return [
            'product_id'             => 'required', 
            'score_date'             => 'required', 
            'expected_return_year'   => 'required'
        ];
    }
}
