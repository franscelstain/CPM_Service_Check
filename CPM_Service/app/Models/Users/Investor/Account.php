<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Account extends Model
{
    protected $table        = 'u_investors_accounts';
    protected $primaryKey   = 'investor_account_id';
    protected $guarded      = ['investor_account_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        //
    }
}