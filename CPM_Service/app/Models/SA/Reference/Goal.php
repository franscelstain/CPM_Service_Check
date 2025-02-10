<?php

namespace App\Models\SA\Reference;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;


class Goal extends Model
{
    protected $table        = 'm_goals';
    protected $primaryKey   = 'goal_id';
    protected $fillable     = ['goal_name', 'description', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
    		'goal_name' => ['required', Rule::unique('m_goals')->ignore($id, 'goal_id')->where(function ($query) {
                return $query->where('is_active', 'Yes');
            })]
    	];
    }
}
