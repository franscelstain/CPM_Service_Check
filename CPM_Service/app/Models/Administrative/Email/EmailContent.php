<?php

namespace App\Models\Administrative\Email;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class EmailContent extends Model
{
    protected $table        = 'c_email_contents';
    protected $primaryKey   = 'email_content_id';
    protected $fillable 	= ['layout_id', 'email_content_name', 'email_content_text', 'email_subject', 'email_change', 'created_by', 'created_host'];
    protected $casts        = ['email_change' => 'array'];

    public static function rules ($id = null)
    {
        return [
            'email_content_name'    => ['required', 'max:50', Rule::unique('c_email_contents')->ignore($id, 'email_content_id')->where(function ($query) {
                                            return $query->where('is_active', 'Yes');
                                       })],
            'email_content_text'    => 'required',
            'email_subject'         => 'required',
            'layout_id'             => 'required'
        ];
    }
}
