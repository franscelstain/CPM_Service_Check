<?php

namespace App\Models\SA\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AssetClass extends Model
{
    protected $table        = 'm_asset_class';
    protected $primaryKey   = 'asset_class_id';
    protected $guarded      = ['asset_class_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules($id = null)
    {
        return [
            'asset_class_code'  => ['required', Rule::unique('m_asset_class')->ignore($id, 'asset_class_id')->where(function ($query){
                                        return $query->where('is_active', 'Yes');
                                   })],
            'asset_category_id' => 'required',
            'asset_class_name'  => 'required',
            'asset_class_color' => 'required',
            'asset_class_type'  => 'required'
        ];
    }
}
