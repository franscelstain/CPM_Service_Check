<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Religion extends Model
{
    protected $table        = 'm_religions';
    protected $primaryKey   = 'religion_id';
    protected $fillable     = ['religion_name', 'ext_code', 'description', 'is_data', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'religion_name' => ['required', Rule::unique('m_religions')->ignore($id,'religion_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })]
    	];
    }
}
