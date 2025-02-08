<?php

namespace App\Models\Administrative\Config;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Config extends Model
{
    protected $table        = 'c_config';
    protected $primaryKey   = 'config_id';
    protected $fillable 	= ['config_name', 'config_value', 'config_type', 'config_img', 'created_by', 'created_host'];

    public static function rules($id=null, $request) 
    {
    	$rules = [
    		'config_name'	=> ['required', Rule::unique('c_config')->ignore($id, 'config_id')->where(function ($query){
                                    return $query->where('is_active', 'Yes');
                               })],
            'config_img'    => 'image|mimes:jpeg,png,jpg,gif'
    	];

        if ($request->input('config_name') == 'SpeedMarquee')
        {
            $rules = array_merge($rules, ['config_value' => 'required|min:1|max:10']);
        }
        else
        {
            $rules = array_merge($rules, ['config_value' => 'required|min:1']);
        }

        return $rules;
    }
}
