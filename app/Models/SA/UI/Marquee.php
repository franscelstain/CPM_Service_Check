<?php

namespace App\Models\SA\UI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Marquee extends Model
{
    protected $table        = 'm_marquee';
    protected $primaryKey   = 'marquee_id';
    protected $fillable     = ['marquee_name', 'marquee_slug', 'marquee_text', 'sequence_to', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'marquee_name'  => ['required', Rule::unique('m_marquee')->ignore($id, 'marquee_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'marquee_text'  => 'required|max:100',
            'sequence_to'   => 'required|numeric|min:1'

    	];
    }
}
