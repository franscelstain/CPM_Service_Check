<?php

namespace App\Models\SA\UI\Landings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Menu extends Model
{
    protected $table        = 'm_menus_landings';
    protected $primaryKey   = 'menu_id';
    protected $fillable     = ['menu_name', 'sequence_to', 'description', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'menu_name'     => ['required', Rule::unique('m_menus_landings')->ignore($id, 'menu_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'sequence_to'   => 'required|numeric|min:1',
            'description'   => 'string|numeric|max:160'
    	];
    }
}
