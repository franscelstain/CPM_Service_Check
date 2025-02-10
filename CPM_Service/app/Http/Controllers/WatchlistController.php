<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AppController;
use App\Models\Transaction\WatchListInvestor;
use App\Models\SA\Assets\Portfolio\ModelMapping;
use App\Models\SA\Assets\Portfolio\Models;
use App\Models\SA\Assets\Products\Fee;
use App\Models\SA\Assets\Products\Price;
use App\Models\SA\Assets\Products\Product;
use Illuminate\Http\Request;

class WatchlistController extends AppController
{
    public function product()
    {
        try
        {
            $product =  WatchListInvestor::select()
                        ->leftJoin('m_products as b', 't_watchlist_investors.key_id', '=', 'b.product_id')
                        ->leftJoin('m_asset_class as c', function($qry) { $qry->on('b.asset_class_id', '=', 'c.asset_class_id')->where('c.is_active', 'Yes'); })
                        ->leftJoin('m_asset_categories as d', function($qry) { $qry->on('c.asset_category_id', '=', 'd.asset_category_id')->where('d.is_active', 'Yes'); })
                        ->leftJoin('m_issuer as e', function($qry) { $qry->on('b.issuer_id', '=', 'e.issuer_id')->where('e.is_active', 'Yes'); })
                        ->leftJoin('m_products_period as f', function($qry) { $qry->on('b.product_id', '=', 'f.product_id')->where('f.is_active', 'Yes'); })
                        ->join('u_investors as g', 't_watchlist_investors.investor_id', '=', 'g.investor_id')
                        ->where([['t_watchlist_investors.is_active', 'Yes'], ['t_watchlist_investors.watchlist_type', 'product'], ['g.is_active', 'Yes'], ['b.is_active', 'Yes']])
                        ->get();

             return $this->app_response('Product', $product);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function goals()
    {
        try
        {
            $goals =  WatchListInvestor::select()
                        ->join('u_investors as b', 't_watchlist_investors.investor_id', '=', 'b.investor_id')
                        ->leftJoin('t_goal_investment as c', function($qry) { return $qry->on('b.investor_id', '=', 'c.investor_id')->where('c.is_active', 'Yes'); })
                        ->leftJoin('m_models as d', function($qry) { return $qry->on('c.model_id', '=', 'd.model_id')->where('d.is_active', 'Yes'); })
                        ->leftJoin('m_risk_profiles as e', function($qry) { return $qry->on('c.profile_id', '=', 'e.profile_id')->where('e.is_active', 'Yes'); })
                        ->leftJoin('m_goals as f', function($qry) {return $qry->on('c.goal_id', '=', 'f.goal_id')->where('f.is_active', 'Yes'); })
                        ->leftJoin('m_trans_reference as g', function($qry) { return $qry->on('c.status_id', '=', 'g.trans_reference_id')->where([['g.reference_type', 'Goals Status'], ['g.is_active', 'Yes']]); })
                       ->where([['t_watchlist_investors.is_active', 'Yes'], ['t_watchlist_investors.watchlist_type', 'goals'], ['g.is_active', 'Yes']])
                       ->get();

             return $this->app_response('Goals', $goals);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }


    public function saveWatchlist()
    {
        try
        {
            $product    = Product::select('m_products.product_id', 'product_name', 'b.asset_class_name', 'c.issuer_name', 'c.issuer_logo', 'd.return_1day', 'd.price')
                        ->leftJoin('m_asset_class as b', function($qry) { $qry->on('m_products.asset_class_id', '=', 'b.asset_class_id')->where('b.is_active', 'Yes'); })
                        ->leftJoin('m_asset_categories as e', function($qry) { $qry->on('b.asset_category_id', '=', 'e.asset_category_id')->where('e.is_active', 'Yes'); })
                        ->get();         

            return $this->app_response($product);

            // foreach ($assets as $ast) 
            // {
            //     if (!empty($ast))
            //     {
            //         $msg = 'NAV Produk '.$ast->product_name.' turun '.number_format($ast->return_1day).'.';
                        
            //         $notif[] = $msg;                                              
            //     }
            //     $act    = empty($intvl->id) ? 'created' : 'updated';
            //     $data = [
            //         'investor_id'   => $ast['investor_id'],
            //         'notif_title'   => 'Manage Nav',
            //         'notif_desc'    => $msg,
            //         'notif_email'   => '1',
            //         'notif_api'     => '0',
            //         'notif_mobile'  => '',
            //         $act.'_by'      => 'System',
            //         $act.'_host'    => '::1',
            //         'is_active'     => 'Yes'

            //             ];
            //         WatchListInvestor::create($data);
            // }
            
            // return $this->app_response($product);        
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }
}