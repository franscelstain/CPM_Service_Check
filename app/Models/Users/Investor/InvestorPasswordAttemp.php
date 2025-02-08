<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class InvestorPasswordAttemp extends Model
{
    protected $table        = 'u_investors_password_attempt';
    protected $primaryKey   = 'investor_password_attempt_id';
    protected $guarded      = ['investor_password_attempt_id'];
    
    public static function rules()
    {
        return[];
    }
}