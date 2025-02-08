<?php

namespace App\Models\SA\Reference\Bank;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AccountType extends Model
{
    protected $table        = 'm_account_types';
    protected $primaryKey   = 'account_type_id';
    protected $guarded      = ['account_type_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            'account_type_name' => ['required', Rule::unique('m_account_types')->ignore($id, 'account_type_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })]
        ];
    }
}
