<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class InvestmentObjective extends Model
{
    protected $table        = 'm_investment_objectives';
    protected $primaryKey   = 'investobj_id';
    protected $fillable     = ['investobj_name', 'ext_code', 'is_data', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'investobj_name'    => ['required', Rule::unique('m_investment_objectives')->ignore($id, 'investobj_id')->where(function ($query) { return $query->where('is_active', 'Yes'); })]
        ];
    }
}
