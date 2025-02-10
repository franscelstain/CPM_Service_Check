<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class MaritalStatus extends Model
{
    protected $table        = 'm_marital_status';
    protected $primaryKey   = 'marital_id';
    protected $fillable     = ['marital_name', 'ext_code', 'description', 'is_data', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'marital_name'  => ['required', Rule::unique('m_marital_status')->ignore($id, 'marital_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })]
    	];
    }
}
