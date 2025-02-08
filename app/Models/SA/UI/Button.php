<?php

namespace App\Models\SA\UI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Button extends Model
{
    protected $table        = 'm_buttons';
    protected $primaryKey   = 'button_id';
    protected $fillable     = ['button_name', 'action', 'action_per_data', 'icon', 'sequence_to', 'description', 'created_by', 'created_host'];
    
    public static function rules($id = null)
    {
        return [
            'button_name'       => ['required', Rule::unique('m_buttons')->ignore($id, 'button_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })],
            'action'            => 'required',
            'action_per_data'   => 'required',
            'icon'              => 'required',
            'sequence_to'       => 'required'
        ];
    }
}