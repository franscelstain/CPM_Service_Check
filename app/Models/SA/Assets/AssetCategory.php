<?php

namespace App\Models\SA\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AssetCategory extends Model
{
    protected $table        = 'm_asset_categories';
    protected $primaryKey   = 'asset_category_id';
    protected $fillable     = ['asset_category_name', 'diversification_account', 'description', 'created_by', 'created_host'];
    
    public static function rules($id = null)
    {
        return [
            'asset_category_name'   => ['required', Rule::unique('m_asset_categories')->ignore($id, 'asset_category_id')->where(function ($query){
                                            return $query->where('is_active', 'Yes');
                                       })]
        ];
    }
}
