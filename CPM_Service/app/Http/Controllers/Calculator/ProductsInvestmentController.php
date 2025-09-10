<?php

namespace App\Http\Controllers\Calculator;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\Products\DocumentView;
use App\Models\SA\Assets\Products\Product;
use Illuminate\Http\Request;

class ProductsInvestmentController extends AppController
{
    public function calculate(Request $request)
    {
        try
        {
            $prj_amt    = $inv_amt = $idx_amt = $period = [];
            $id         = $request->product_id;
            $inv_typ    = $request->investment_type;
            $amount     = intval($request->investment_amount);
            $time       = intval($request->time_period);
            $product    = Product::select('m_products.product_id', 'm_products.product_name', 'b.asset_class_name', 'c.issuer_logo', 'd.profile_name', 'e.expected_return_year', 'e.expected_return_month', 'f.asset_category_name')
                        ->leftJoin('m_asset_class as b', function($qry) { $qry->on('m_products.asset_class_id', '=', 'b.asset_class_id')->where('b.is_active', 'Yes'); })
                        ->leftJoin('m_issuer as c', function($qry) { $qry->on('m_products.issuer_id', '=', 'c.issuer_id')->where('c.is_active', 'Yes'); })
                        ->leftJoin('m_risk_profiles as d', function($qry) { $qry->on('m_products.profile_id', '=', 'd.profile_id')->where('d.is_active', 'Yes'); })
                        ->leftJoin('t_products_scores as e', function($qry) { $qry->on('m_products.product_id', '=', 'e.product_id')->where('e.is_active', 'Yes'); })
                        ->leftJoin('m_asset_categories as f', function($qry) { $qry->on('b.asset_category_id', '=', 'f.asset_category_id')->where('f.is_active', 'Yes'); })
                        ->where([['m_products.product_id', $id], ['m_products.is_active', 'Yes']])
                        ->first();
            $index      = Product::select('d.expected_return_year', 'd.expected_return_month')
                        ->join('m_asset_class as b', 'm_products.asset_class_id', 'b.asset_class_id')
                        ->join('m_asset_categories as c', 'b.asset_category_id', 'c.asset_category_id')
                        ->join('t_products_scores as d', 'm_products.product_id', 'd.product_id')
                        ->where([['m_products.is_active', 'Yes'], ['c.asset_category_name', 'Index'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes'], ['d.is_active', 'Yes']])
                        ->first();
            $document   = !empty($product->product_id) ? DocumentView::select('document_id as id', 'asset_document_name as name', 'document_link as link')->where('product_id', $product->product_id)->get() : [];
            $exp_yr     = !empty($product->expected_return_year) ? $product->expected_return_year : 0;
            $exp_yr_idx = !empty($index->expected_return_year) ? $index->expected_return_year : 0;
            $exp_mth    = !empty($product->expected_return_month) ? $product->expected_return_month : 0;
            $exp_mth_idx= !empty($index->expected_return_month) ? $index->expected_return_month : 0;
            $future_val = $inv_typ == 'Recurring' ? $exp_mth != 0 ? ($amount*(1+$exp_mth)) * (pow(1+$exp_mth, 12*$time)-1) / $exp_mth : 0 : $amount*pow(1+$exp_yr, $time);
            
            for ($i = 1; $i <= $time; $i++)
            {
                $prj_amt[]  = $inv_typ == 'Recurring' ? $exp_mth != 0 ? ($amount*(1+$exp_mth)) * (pow(1+$exp_mth, 12*$i)-1) / $exp_mth : 0 : $amount*pow(1+$exp_yr, $i);
                $inv_amt[]  = $inv_typ == 'Recurring' ? $amount*$i*12 : $amount;
                $idx_amt[]  = $inv_typ == 'Recurring' ? $exp_mth_idx != 0 ? ($amount*(1+$exp_mth_idx)) * (pow(1+$exp_mth_idx, 12*$i)-1) / $exp_mth_idx : 0 : $amount*pow(1+$exp_yr_idx, $i);
                $period[]   = date('M Y', strtotime($i . ' year'));
            }
            
            $result = array_merge(json_decode($product, true), [
                'amount'            => $amount,
                'document'          => $document,
                'exp_year'          => $exp_yr * 100,
                'future_value'      => $future_val,
                'index_amount'      => $idx_amt,
                'investment_amount' => $inv_amt,
                'period'            => $period,
                'projected_amount'  => $prj_amt
            ]);
            
            return $this->app_response('data', $result);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}