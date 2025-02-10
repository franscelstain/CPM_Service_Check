<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Qna extends Model
{
    protected $table        = 't_questions';
    protected $primaryKey   = 'question_id';
    protected $fillable     = ['fullname', 'investor_id', 'email', 'phone', 'subject', 'message', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'fullname'  => 'required',
            'email'     => 'required|email',
            'phone'     => 'required|max:13|min:10',
            'subject'   => 'required',
            'message'   => 'required'
    	];
    }
}
