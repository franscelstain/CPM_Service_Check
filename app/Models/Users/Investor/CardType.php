<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CardType extends Model
{
    protected $table        = 'u_investors_card_types';
    protected $primaryKey   = 'investor_card_type_id';
    protected $guarded      = ['investor_card_type_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules($id = null)
    {
        return [
            
        ];
    }
}