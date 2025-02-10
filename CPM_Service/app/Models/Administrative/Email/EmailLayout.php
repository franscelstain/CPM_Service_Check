<?php

namespace App\Models\Administrative\Email;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class EmailLayout extends Model
{
    protected $table        = 'c_email_layouts';
    protected $primaryKey   = 'layout_id';
    protected $fillable 	= ['layout_title', 'layout_content', 'layout_change', 'created_by', 'created_host'];
    protected $casts        = ['layout_change' => 'array'];

    public static function rules ($id = null)
    {
        return [
            'layout_title'      => ['required', 'max:50', Rule::unique('c_email_layouts')->ignore($id, 'layout_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })],
            'layout_content'    => 'required',
            'layout_change'     => 'required|array'
        ];
    }
}
