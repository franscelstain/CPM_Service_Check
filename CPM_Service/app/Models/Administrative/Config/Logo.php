<?php

namespace App\Models\Administrative\Config;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Logo extends Model
{
    protected $table        = 'c_logo';
    protected $primaryKey   = 'logo_id';
    protected $fillable 	= ['logo_color', 'logo_color_white', 'logo_white', 'logo_only', 'favicon', 'created_by', 'created_host'];

    public static function rules($id = null, $request)
    {
    	$rules 	= [];
	    $logo 	= ['favicon', 'logo_color', 'logo_color_white', 'logo_only', 'logo_white'];
        foreach ($logo as $lg)
        {
            if ($request->hasFile($lg))
            { 
            	$rules = $lg != 'favicon' ? array_merge($rules, [$lg => 'image|mimes:jpeg,png,jpg']) : $rules;
            }
        }
        return $rules;
    }
}
