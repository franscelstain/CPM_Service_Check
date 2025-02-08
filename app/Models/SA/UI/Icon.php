<?php

namespace App\Models\SA\UI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Icon extends Model
{
    protected $table        = 'm_icons';
    protected $primaryKey   = 'icon_id';
    protected $fillable     = ['icon', 'icon_type', 'created_by', 'created_host'];
    
    public static function rules($id = null)
    {
        return ['icon' => ['required', Rule::unique('m_icons')->ignore($id, 'icon_id')->where(function ($query) {
            return $query->where('is_active', 'Yes');
        })]];
    }
}