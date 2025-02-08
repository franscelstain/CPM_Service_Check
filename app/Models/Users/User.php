<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class User extends Model
{
    protected $table        = 'u_users';
    protected $primaryKey   = 'user_id';
    protected $guarded      = ['user_id', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
	
	public static function rules($id = null)
	{
		$rule = [
            'email'     => ['required', Rule::unique('u_users')->ignore($id, 'user_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                    })],
            'fullname'  => 'required|max:50',
            'password'  => 'min:8|regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9]).{6,}$/'	
		];
		
		if($request->file('photo_profile'))
        {
            $rule = array_merge($rule, ['photo_profile' => 'image|mimes:jpeg,png,jpg']);
        }        
        return $rule;
	}
}
