<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CardPriority extends Model
{
    protected $table        = 'u_investors_card_priorities';
    protected $primaryKey   = 'investor_card_id';
    protected $guarded      = ['investor_card_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules($id = null)
    {
        return [
            
        ];
    }
}