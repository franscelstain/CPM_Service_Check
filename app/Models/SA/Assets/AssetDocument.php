<?php

namespace App\Models\SA\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AssetDocument extends Model
{
    protected $table        = 'm_asset_documents';
    protected $primaryKey   = 'asset_document_id';
    protected $guarded      = ['asset_document_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules($id = null)
    {
        return [
            'asset_document_name'   => ['required', Rule::unique('m_asset_documents')->ignore($id, 'asset_document_id')->where(function ($query){
                                            return $query->where('is_active', 'Yes');
                                       })],
            'asset_category_id'     => 'required'
        ];
    }
}
