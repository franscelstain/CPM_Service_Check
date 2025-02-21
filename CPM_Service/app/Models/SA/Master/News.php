<?php

namespace App\Models\SA\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class News extends Model
{
    protected $table        = 'm_news';
    protected $primaryKey   = 'news_id';
    protected $fillable     = ['author_id', 'news_title', 'news_slug', 'news_content', 'news_image', 'published', 'published_date', 'published_to', 'is_promo', 'created_by', 'created_host'];

    public static function rules($id = null, $request)
    {
        $rules = [
            'news_slug'         => ['required', Rule::unique('m_news')->ignore($id, 'news_id')->where(function ($query) { 
                                        return $query->where('is_active', 'Yes'); 
                                   })], 
            'news_content'      => 'required', 
            'news_title'        => 'required|max:255',
            'author_id'         => 'required',
            'published'         => 'required',
            'published_to'      => 'required', 
            'published_date'    => 'required|date|date_format:Y-m-d',
            'is_promo'          => 'required'
        ];

        if($request->hasFile('news_image'))
        {
            $rules = array_merge($rules, ['news_image' => 'required|image|mimes:jpeg,png,jpg,gif']);
        }

        return $rules;
    }
}
