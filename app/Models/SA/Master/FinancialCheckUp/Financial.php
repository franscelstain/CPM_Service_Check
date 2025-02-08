<?php

namespace App\Models\SA\Master\FinancialCheckUp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Financial extends Model
{
    protected $table        = 'm_financials';
    protected $primaryKey   = 'financial_id';
    protected $guarded      = ['financial_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null, $request)
    {
        return [
            'financial_name'    => ['required', Rule::unique('m_financials')->ignore($id, 'financial_id')->where(function ($query) use($request) {
                                        return $query->where([['is_active', 'Yes'], ['financial_type', $request->input('financial_type')]]);
                                   })],
            'financial_type'    => 'required',
            'sequence_to'       => 'required|numeric|min:1',
            'description'       => 'max:160'
        ];
    }
}
