<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Document extends Model
{
    protected $table        = 'm_products_documents';
    protected $primaryKey   = 'document_id';
    protected $guarded      = ['document_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules($id = null)
    {
        return [
            'product_id'        => 'required',
            'asset_document_id' => 'required'
        ];
    }
}
