<?php

namespace App\Models\SA\UI\Menus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Menu extends Model
{
    protected $table        = 'm_menus';
    protected $primaryKey   = 'menu_id';
    protected $guarded      = ['menu_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    protected $casts        = ['button' => 'array'];
    
    public static function rules($id = null, $request)
    {
        return [
            'menu_name'     => ['required', Rule::unique('m_menus')->ignore($id, 'menu_id')->where(function ($query) use($request) {
                                    $parent_id  = $request->input('parent_id');
                                    $group_id   = $request->input('group_id');

                                    $find       = $query->where([['is_active', 'Yes'], ['usercategory_id', $request->input('usercategory_id')]]);
                                    $find       = $parent_id > 0 ? $find->where(function ($qry) use ($parent_id) { $qry->where('parent_id', $parent_id)
                                                ->orWhereNull('parent_id'); }) : $find->whereNull('parent_id');
                                    $find       = $group_id > 0 ? $find->where(function ($qry) use ($group_id) { $qry->where('group_id', $group_id)
                                                ->orWhereNull('group_id'); }) : $find->whereNull('group_id');
                
                                    return $find;
                               })],
            'slug'    => [Rule::unique('m_menus')->ignore($id, 'menu_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'sequence_to'   => 'required|numeric|min:1',
            'description'   => 'required'
        ];
    }
}