<?php

namespace App\Models\SA\Master\ME;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class HistInflation extends Model
{
    protected $table        = 'm_histinflation';
    protected $primaryKey   = 'histinflation_id';
    protected $fillable     = ['year', 'avg_inflation', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'year'          => ['required','numeric', 'min:0',  Rule::unique('m_histinflation')->ignore($id, 'histinflation_id')->where(function ($query) { return $query->where('is_active', 'Yes');
                               })],
            'avg_inflation' => 'required|numeric|min:0|max:100'
		];
    }
}
