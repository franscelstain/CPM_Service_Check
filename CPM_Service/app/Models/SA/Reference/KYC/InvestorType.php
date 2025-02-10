<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class InvestorType extends Model
{
    protected $table        = 'm_investor_types';
    protected $primaryKey   = 'investor_type_id';
    protected $fillable     = ['investor_type_name', 'ext_code', 'is_data', 'created_by', 'created_host'];
    
    public static function rules($id = null,$request)
    {
        return [
            'investor_type_name'    => ['required', Rule::unique('m_investor_types')->ignore($id, 'investor_type_id')->where(function ($query) use($request) {
                                            return $query->where([['is_active', 'Yes']]);
                                       })]
        ];
    }
}
