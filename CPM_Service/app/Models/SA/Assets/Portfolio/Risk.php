<?php

namespace App\Models\SA\Assets\Portfolio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Risk extends Model
{
    protected $table        = 'm_portfolio_risk';
    protected $primaryKey   = 'portfolio_risk_id';
    protected $fillable     = ['portfolio_risk_id', 'created_by', 'created_host'];
    
    public static function rules($id = null,$request)
    {
        return [
            
        ];
    }
}
