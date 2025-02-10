<?php

namespace App\Models\SA\UI\Menus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class MenuGroup extends Model
{
    protected $table        = 'm_menus_groups';
    protected $primaryKey   = 'group_id';
    protected $fillable     = ['group_name', 'sequence_to', 'description', 'created_by', 'created_host'];
	
	public static function rules($id = null)
	{
		return [
            'group_name'    => ['required', Rule::unique('m_menus_groups')->ignore($id, 'group_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'sequence_to'   => 'required|numeric|min:1'
		];
	}
}
