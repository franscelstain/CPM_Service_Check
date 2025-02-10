<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class InvestorPasswordRenewal extends Model
{
    protected $table        = 'u_investors_password_renewal';
    protected $primaryKey   = 'investor_password_renewal_id';
    protected $guarded      = ['investor_password_renewal_id'];
    
    public static function rules()
    {
        return[];
    }
}