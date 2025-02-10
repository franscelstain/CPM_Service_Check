<?php

namespace App\Models\SA\Reference\Bank;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Branch extends Model
{
    protected $table        = 'm_bank_branches';
    protected $primaryKey   = 'bank_branch_id';
    protected $guarded      = ['bank_branch_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            'branch_name'       => ['required', Rule::unique('m_bank_branches')->ignore($id, 'bank_branch_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })],
            // 'bank_id'           => 'required',
            'branch_city'       => 'required',
            'branch_address'    => 'required',
        ];
    }
}
