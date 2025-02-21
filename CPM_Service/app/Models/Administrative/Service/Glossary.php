<?php

namespace App\Models\Administrative\Service;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Glossary extends Model
{
    protected $table        = 'm_glossaries';
    protected $primaryKey   = 'glossary_id';
    protected $fillable     = ['glossary_code', 'glossary_name', 'glossary_text', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'glossary_code' => ['required', 'string', 'max:20', Rule::unique('m_glossaries')->ignore($id, 'glossary_id')
                                ->where(function ($query) { return $query->where('is_active', 'Yes'); })],
            'glossary_name' => ['required', Rule::unique('m_glossaries')->ignore($id, 'glossary_id')
                                ->where(function ($query) { return $query->where('is_active', 'Yes'); })],
            'glossary_text' => 'required|max:160'
    	];
    }
}
