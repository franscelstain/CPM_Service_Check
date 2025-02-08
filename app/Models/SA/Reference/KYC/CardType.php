<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CardType extends Model
{
    protected $table        = 'u_investors_card_types';
    protected $primaryKey   = 'investor_card_type_id';
    protected $fillable     = ['card_type_number', 'card_type_name', 'is_data', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'card_type_number' => ['required', Rule::unique('u_investors_card_types')->ignore($id,'investor_card_type_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })]
    	];
    }
}
