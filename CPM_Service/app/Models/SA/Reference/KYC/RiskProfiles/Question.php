<?php

namespace App\Models\SA\Reference\KYC\RiskProfiles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Question extends Model
{
    protected $table        = 'm_profile_questions';
    protected $primaryKey   = 'question_id';
    protected $guarded      = ['question_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null, $request)
    {
    	return [
            'question_text'     => ['required', Rule::unique('m_profile_questions')->ignore($id, 'question_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })],
            'question_title'    => ['required', Rule::unique('m_profile_questions')->ignore($id, 'question_id')->where(function ($query) use($request) {
                                        return $query->where([['is_active', 'Yes'], ['investor_type_id', $request->input('investor_type_id')]]);
                                   })],
            'answer_icon'       => 'required',
            'sequence_to'       => 'required|numeric|min:1',
            'answer_text'       => 'required|array',
            'answer_text.*'     => 'required|distinct',
            'answer_score'      => 'required|array',
            'icon'              => 'array',
            'icon.*'            => 'image|mimes:jpeg,png,jpg,gif'

    	];
    }
}
