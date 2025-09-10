<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Investor extends Model
{
    protected $table        = 'u_investors';
    protected $primaryKey   = 'investor_id';
    protected $guarded      = ['investor_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules($id, $request)
    {
        $rule = ['identity_no' => 'required'];
        if (empty($id))
        {
            return array_merge($rule, ['email' => 'required|email|unique:u_investors', 'password' => 'required|confirmed|min:8']);
        }
        if($request->file('photo_profile'))
        {
            $rule = array_merge($rule, ['photo_profile' => 'image|mimes:jpeg,png,jpg']);
        }        
        return $rule;
    }
}