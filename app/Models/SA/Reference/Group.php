<?php

namespace App\Models\SA\Reference;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;


class Group extends Model
{
    protected $table        = 'm_reference_groups';
    protected $primaryKey   = 'group_id';
    protected $guarded      = ['group_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
    	return [
    		'group_name' => ['required', Rule::unique('m_reference_groups')->ignore($id, 'group_id')->where(function ($query) {
                return $query->where('is_active', 'Yes');
            })],
            'description'       => 'max:160'
    	];
    }
}
