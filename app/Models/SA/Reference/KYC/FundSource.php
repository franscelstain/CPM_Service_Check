<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class FundSource extends Model
{
    protected $table        = 'm_fund_sources';
    protected $primaryKey   = 'fund_source_id';
    protected $fillable     = ['fund_source_name', 'ext_code', 'description', 'is_data', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'fund_source_name'  => ['required', Rule::unique('m_fund_sources')->ignore($id, 'fund_source_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })]
    	];
    }
}
