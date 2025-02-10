<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class InvestorPasswordHistories extends Model
{
    protected $table        = 'u_investors_password_histories';
    protected $primaryKey   = 'investor_password_histories_id';
    protected $guarded      = ['investor_password_histories_id'];
    
    public static function rules()
    {
        return[];
    }
}