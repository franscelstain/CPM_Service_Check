<?php

namespace App\Models\SA\UI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SocialMedia extends Model
{
    protected $table        = 'm_social_media';
    protected $primaryKey   = 'socmed_id';
    protected $fillable     = ['socmed_name', 'socmed_slug', 'socmed_icon_landing', 'socmed_icon_colored', 'socmed_view', 'sequence_to', 'created_by', 'created_host'];

    public static function rules($id = null, $request)
    {
    	$rule =  [
            'socmed_name'   => ['required', Rule::unique('m_social_media')->ignore($id, 'socmed_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'socmed_slug'   => 'required', 
            'socmed_view'   => 'required',
            'sequence_to'   => 'required'
    	];

        // if($request->file('socmed_icon_landing')||$request->file('socmed_icon_colored'))
        // {
        //     $rule = array_merge($rule, ['socmed_icon_landing' => 'image|mimes:jpeg,png,jpg,gif,svg',
        //                                     'socmed_icon_colored' => 'image|mimes:jpeg,png,jpg,gif,svg'
        //                                     ]);
        // }
        $rule   = ['socmed_icon_landing', 'socmed_icon_colored'];
        foreach ($rule as $lg)
        {
            if ($request->hasFile($lg))
            { 
                $rule = array_merge($rule, [$lg => 'image|mimes:jpeg,png,jpg']);
            }
        }
        return $rule;
    }
}
