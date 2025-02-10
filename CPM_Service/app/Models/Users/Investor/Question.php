<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Question extends Model
{
    protected $table        = 'u_investors_questions';
    protected $primaryKey   = 'investor_question_id';
    protected $guarded      = ['investor_question_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        //
    }
}