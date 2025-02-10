<?php

namespace App\Models\Administrative\Service\Center;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Help extends Model
{
    protected $table        = 'c_help_center';
    protected $primaryKey   = 'help_id';
    protected $fillable		= ['category_id', 'help_name', 'help_text', 'slug', 'created_by', 'created_host'];
    
    public static function rules($id = null)
    {
        return [
            'slug'          => ['required', Rule::unique('c_help_center')->ignore($id, 'help_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'category_id'   => 'required',
            'help_name'     => ['required', Rule::unique('c_help_center')->ignore($id, 'help_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'help_text'     => 'required'
        ];
    }
}