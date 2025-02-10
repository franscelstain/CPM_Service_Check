<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;

class ScoreHist extends Model
{
    protected $table        = 't_products_scores_histories';
    protected $primaryKey   = 'product_score_hist_id';
    protected $fillable     = ['product_score_id', 'product_id', 'score_date', 'expected_return', 'standard_deviation', 'sharpe_ratio', 'volatility', 'sortino_ratio', 'jensen_alpha', 'capm', 'roy_safety_ratio', 'aum', 'return_year_min',  'return_year_max', 'return_month_min', 'return_month_max', 'created_by', 'created_host'];
}
