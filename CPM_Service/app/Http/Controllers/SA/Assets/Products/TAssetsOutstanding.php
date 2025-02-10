<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

t_assets_outstanding
class TAssetsOutstanding extends Model
{
    protected $table        = 't_assets_outstanding';
    protected $primaryKey   = 'outstanding_id';
    protected $guarded      = ['outstanding_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            'product_id'   => ['required', Rule::unique('t_assets_outstanding')->ignore($id, 'outstanding_id')->where(function ($query) { 
                                                return $query->where('is_active', 'Yes');
                                           })]
        ];
    }
}
