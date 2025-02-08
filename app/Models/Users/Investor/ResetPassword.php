<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ResetPassword extends Model
{
    protected $table        = 'u_investors_password';
    protected $primaryKey   = 'id';
    protected $guarded      = ['id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules($id = null)
    {
        return [
            
        ];
    }
}