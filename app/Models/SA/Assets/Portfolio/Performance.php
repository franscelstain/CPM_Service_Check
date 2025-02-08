<?php

namespace App\Models\SA\Assets\Portfolio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Performance extends Model
{
    protected $table        = 'm_portfolio_performance';
    protected $primaryKey   = 'portfolio_perfrmance_id';
    protected $fillable     = ['portfolio_perfrmance_id', 'investor_id', 'portfolio_risk_id', 'goal_invest_id', 'portfolio_id', 'created_by', 'created_host'];
    
    public static function rules($id = null,$request)
    {
        return [
            
        ];
    }
}
