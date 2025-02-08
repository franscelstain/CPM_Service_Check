<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class UserPasswordAttemp extends Model
{
    protected $table        = 'u_users_password_attempt';
    protected $primaryKey   = 'user_password_attempt_id';
    protected $guarded      = ['user_password_attempt_id'];
    
    public static function rules()
    {
        return[];
    }
}