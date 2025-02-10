<?php
namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\Portfolio\AllocationWeight;
use App\Models\SA\Assets\Products\Product;
use App\Models\SA\Assets\Portfolio\AllocationWeightDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PortfolioModelController extends AppController
{
    public function index()
    {
        try
        {
            $data =  AllocationWeight::selectRaw("allocation_weight_id, effective_date, m.model_name, m_portfolio_allocations_weights.created_by, expected_return_year, volatility, m_portfolio_allocations_weights.portfolio_risk_id, portfolio_risk_name")
                                    ->join('m_models as m', 'm.model_id', '=', 'm_portfolio_allocations_weights.model_id')  
                                    ->join('m_portfolio_risk as mpr', 'mpr.portfolio_risk_id', '=', 'm_portfolio_allocations_weights.portfolio_risk_id')
                                    ->where([['m_portfolio_allocations_weights.is_active', 'Yes'], ['m.is_active', 'Yes'], ['mpr.is_active', 'Yes'], ['effective_date', $this->effectiveDate() ]])
                                    ->get();
            foreach($data as $dt) {
                $id = $dt->allocation_weight_id;
                $dt->id = $id;
                $total = $this->model_total($id);
                $dt->total_product  = $total['total_product'];
                $dt->total_asset_class    = $total['total_asset_class'];
            }

            return $this->app_response('Portfolio', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function detail($id)
    {
        try
        {
            //portfolio model head
            $head =  AllocationWeight::selectRaw("allocation_weight_id, effective_date, m.model_name, m_portfolio_allocations_weights.created_by, expected_return_year, volatility, m_portfolio_allocations_weights.portfolio_risk_id, portfolio_risk_name")
                                    ->join('m_models as m', 'm.model_id', '=', 'm_portfolio_allocations_weights.model_id')  
                                    ->join('m_portfolio_risk as mpr', 'mpr.portfolio_risk_id', '=', 'm_portfolio_allocations_weights.portfolio_risk_id')
                                    ->where([['allocation_weight_id', $id], ['m_portfolio_allocations_weights.is_active', 'Yes'], ['m.is_active', 'Yes'], ['mpr.is_active', 'Yes'], ['effective_date', $this->effectiveDate() ]])
                                    ->first();
            $total = $this->model_total($id);
            $head['effective_date']         = $this->effectiveGlobalDate();
            $head['total_product']          = $total['total_product'];
            $head['total_asset_class']      = $total['total_asset_class'];
            $head['total_asset_category']   = $total['total_asset_category'];
            
            //portfolio model detail
            $detail =  AllocationWeightDetail::selectRaw("mp.product_id, product_code,product_name,mac.asset_class_id,mac.asset_class_name,mact.asset_category_id, mact.asset_category_name, expected_return_year, weight as allocation, standard_deviation as volatility, mi.issuer_id, mi.issuer_logo, asset_class_color")   
                                    ->join('m_products as mp', 'mp.product_id', '=', 'm_portfolio_allocations_weights_detail.product_id')
                                    ->join('m_asset_class as mac', 'mac.asset_class_id', '=', 'mp.asset_class_id') 
                                    ->join('m_asset_categories as mact', 'mact.asset_category_id', '=', 'mac.asset_category_id') 
                                    ->join('t_products_scores as tps', 'tps.product_id', '=', 'mp.product_id')
                                    ->join('m_issuer as mi', 'mi.issuer_id', '=', 'mp.issuer_id') 
                                    ->where([['allocation_weight_id', $id], ['m_portfolio_allocations_weights_detail.is_active', 'Yes'], ['mp.is_active', 'Yes']])
                                    ->get();
                                    
            return $this->app_response('Portfolio', ['head'=>$head, 'detail'=>$detail]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function model_product()
    {
        try
        {
            $data =  Product::select('m_products.product_name', 'b.asset_class_name', 'd.issuer_name', 'd.issuer_logo', 'e.price_value', 'f.return_1day','f.return_1year', 'g.standard_deviation')
                    ->leftJoin('m_asset_class as b', function($qry) { return $qry->on('m_products.asset_class_id', '=', 'b.asset_class_id')->where('b.is_active', 'Yes'); })
                    ->leftJoin('m_asset_categories as c', function($qry) { return $qry->on('b.asset_category_id', '=', 'c.asset_category_id')->where('c.is_active', 'Yes'); })
                    ->leftJoin('m_issuer as d', function($qry) { return $qry->on('m_products.issuer_id', '=', 'd.issuer_id')->where('d.is_active', 'Yes'); })
                    ->leftJoin('m_products_prices as e', function($qry) { return $qry->on('m_products.product_id', '=', 'e.product_id')->where('e.is_active', 'Yes'); })
                    ->leftJoin('m_products_period as f', function($qry) { return $qry->on('m_products.product_id', '=', 'f.product_id')->where('f.is_active', 'Yes'); })
                    ->leftJoin('t_products_scores as g', function($qry) { return $qry->on('m_products.product_id', '=', 'g.product_id')->where('g.is_active', 'Yes'); })
                    // ->where([['m_products.is_active', 'Yes'], ['e.price_date', date('Y-m-d', strtotime('-1 day'))], ['f.period_date', $this->app_date()], ['g.score_date', $this->app_date()]])
                    ->where([['m_products.is_active', 'Yes'], ['e.price_date', date('Y-m-d', strtotime('-1 day'))]])
                    ->get();
            return $this->app_response('Product Model', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    private function model_total($id) {
        try
        {
            return [
                'total_product'         => AllocationWeightDetail::where([['allocation_weight_id', $id], ['is_active', 'Yes']])
                                            ->count(),
                'total_asset_class'     => AllocationWeightDetail::distinct('asset_class_id')
                                            ->join('m_products as mp', 'mp.product_id', '=', 'm_portfolio_allocations_weights_detail.product_id')
                                            ->where([['allocation_weight_id', $id]])
                                            ->count(),
                'total_asset_category'  => AllocationWeightDetail::distinct('mact.asset_category_id')
                                            ->join('m_products as mp', 'mp.product_id', '=', 'm_portfolio_allocations_weights_detail.product_id')
                                            ->join('m_asset_class as mac', 'mac.asset_class_id', '=', 'mp.asset_class_id')
                                            ->join('m_asset_categories as mact', 'mact.asset_category_id', '=', 'mac.asset_category_id') 
                                            ->where([['allocation_weight_id', $id]])
                                            ->count()
            ];
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    private function growth_goals($id, $prd_id, $month)
    {
        $prd    = InvestmentDetail::select('net_amount', 'expected_return_month', 'investment_type')
                ->where([['goal_invest_id', $id], ['product_id', $prd_id], ['is_active', 'Yes']])
                ->first();
        
        if ($prd->investment_type == 'Lumpsum')
            $prj_amt = $prd->net_amount * pow(1 + $prd->expected_return_month, $month);
        else
            $prj_amt = $prd->expected_return_month > 0 ? (($prd->net_amount * (1 + $prd->expected_return_month)) * (pow(1 + $prd->expected_return_month, $month) - 1)) / $prd->expected_return_month : 0;
        
        return $prj_amt;
    }

    private function effectiveDate() {
        // $data = AllocationWeight::selectRaw("max(effective_date)")
        //                             ->where([['is_active', 'Yes']])
        //                             ->first();
        $data =  AllocationWeight::selectRaw("max(effective_date)")
                                    ->join('m_models as m', 'm.model_id', '=', 'm_portfolio_allocations_weights.model_id')  
                                    ->join('m_portfolio_risk as mpr', 'mpr.portfolio_risk_id', '=', 'm_portfolio_allocations_weights.portfolio_risk_id')
                                    ->where([['m_portfolio_allocations_weights.is_active', 'Yes'], ['m.is_active', 'Yes'], ['mpr.is_active', 'Yes'] ])
                                    ->first();
        return  (!empty($data))? $data->max : date('Y-m-d');
    }

    private function effectiveGlobalDate() {
        $data = AllocationWeight::selectRaw("max(effective_date)")
                                    ->where([['is_active', 'Yes']])
                                    ->first();
        return  (!empty($data))? $data->max : date('Y-m-d');
    }
}