<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class DocumentType extends Model
{
    protected $table        = 'm_document_types';
    protected $primaryKey   = 'doctype_id';
    protected $fillable     = ['doctype_name', 'doctype_code', 'show_expired', 'ext_code', 'description', 'is_data', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'doctype_name'  => ['required', Rule::unique('m_document_types')->ignore($id, 'doctype_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })]
    	];
    }
}
