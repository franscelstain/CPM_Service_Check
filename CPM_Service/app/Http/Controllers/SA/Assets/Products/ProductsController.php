<?php

namespace App\Http\Controllers\SA\Assets\Products;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\AssetClass;
use App\Models\SA\Assets\AssetDocument;
use App\Models\SA\Assets\Products\DocumentView;
use App\Models\SA\Assets\Products\Issuer;
use App\Models\SA\Assets\Products\Price;
use App\Models\SA\Assets\Products\Product;
use App\Models\SA\Reference\KYC\RiskProfiles\Profile;
use App\Models\Investor\Financial\Plannig\Current\Outstanding;
use Illuminate\Http\Request;
use Storage;
use Auth;
use DB;

class ProductsController extends AppController
{
    public $table = 'SA\Assets\Products\Product';

    public function index()
    {
        try
        {
            $data = Product::select('m_products.*', 'asset_class_name')
                   ->leftJoin('m_asset_class as b', function($qry) {
                       $qry->on('m_products.asset_class_id', '=', 'b.asset_class_id')->where('b.is_active', 'Yes');
                   })->where('m_products.is_active', 'Yes')->get(); 
            return $this->app_response('Products', ['key' => 'product_id', 'list' => $data]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function allocation(Request $request)
    {
        try
        {
            $data   = Product::select('m_products.*', 'b.asset_class_name')
                    ->join('m_asset_class as b', 'm_products.asset_class_id', '=', 'b.asset_class_id')
                    ->join('m_portfolio_allocations as c', 'b.asset_class_id', '=', 'c.asset_class_id')
                    ->where([['m_products.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes'], ['c.model_id', $request->input('model_id')]])
                    ->get();

            return $this->app_response('data', $data);  
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function asset(Request $request)
    {
        return $this->db_result(['where' => [['asset_class_id', $request->input('asset')]]]);
    }

    public function assets()
    {
        try
        {
            $asset = AssetClass::select('asset_class_type')
                    ->where('m_asset_class.is_active', 'Yes')
                    ->distinct()
                    ->get();
            return $this->app_response('asset', $asset);            
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function benchmark()
    {
        try
        {
            $data   = Product::select('m_products.*')
                    ->join('m_asset_class as b', 'm_products.asset_class_id', '=', 'b.asset_class_id')
                    ->join('m_asset_categories as c', 'b.asset_category_id', '=', 'c.asset_category_id')
                    ->where([['m_products.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                    ->whereIn('asset_category_name', ['Index', 'Index / Benchmark'])
                    ->get();

            return $this->app_response('data', $data);  
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function performance(Request $request)
    {
        try
        {
            $limit      = !empty($request->limit) ? $request->limit : 10;
            $page       = !empty($request->page) ? $request->page : 1;
            /*$product    = Product::select('m_products.product_id', 'product_name', 'b.asset_class_name', 'c.issuer_name', 'c.issuer_logo', 'd.return_1day', 'd.price')
                        ->leftJoin('m_asset_class as b', function($qry) { $qry->on('m_products.asset_class_id', '=', 'b.asset_class_id')->where('b.is_active', 'Yes'); })
                        ->leftJoin('m_issuer as c', function($qry) { $qry->on('m_products.issuer_id', '=', 'c.issuer_id')->where('c.is_active', 'Yes'); })
                        ->leftJoin('m_products_period as d', function($qry) { $qry->on('m_products.product_id', '=', 'd.product_id')->where('d.is_active', 'Yes'); })
                        ->where('m_products.is_active', 'Yes');*/
            $product    = Product::select('m_products.product_id', 'product_name', 'b.asset_class_name', 'c.issuer_name', 'c.issuer_logo', 'd.return_1day', 'd.return_1year', 'd.price', 'f.profile_name')
                        ->leftJoin('m_asset_class as b', function($qry) { $qry->on('m_products.asset_class_id', '=', 'b.asset_class_id')->where('b.is_active', 'Yes'); })
                        ->leftJoin('m_asset_categories as e', function($qry) { $qry->on('b.asset_category_id', '=', 'e.asset_category_id')->where('e.is_active', 'Yes'); })
                        ->leftJoin('m_issuer as c', function($qry) { $qry->on('m_products.issuer_id', '=', 'c.issuer_id')->where('c.is_active', 'Yes'); })
                        ->leftJoin('m_products_period as d', function($qry) { $qry->on('m_products.product_id', '=', 'd.product_id')->where('d.is_active', 'Yes'); })
                        ->leftJoin('m_risk_profiles as f', function($qry) { $qry->on('m_products.profile_id', '=', 'f.profile_id')->where('f.is_active', 'Yes'); })
                        ->where('m_products.is_active', 'Yes')
                        ->where('e.asset_category_name', 'Mutual Fund');
            
            if (!empty($request->exp_rtn) && $request->exp_rtn == 'Yes')
            {
                $product = $product->addSelect('g.expected_return_year')
                                   ->leftJoin('t_products_scores as g', function($qry) { $qry->on('m_products.product_id', '=', 'g.product_id')->where('g.is_active', 'Yes'); })
                                   ->distinct();
            }
            
            if (!empty($request->search)){
                $product = $product->where(function($qry) use ($request) {
                        $qry->where('m_products.product_name', 'ilike', '%'. $request->search .'%')
                        ->orWhere('c.issuer_name', 'ilike', '%'. $request->search .'%');
                });
            }                
            if (!empty($request->asset_class))
                $product = $product->where('b.asset_class_id', $request->asset_class);
            if (!empty($request->price_minimum))
                $product = $product->where('d.price', '>=', $request->price_minimum);
            if (!empty($request->price_maximum))
                $product = $product->where('d.price', '<=', $request->price_maximum);

            return $this->app_response('Product Performance', $limit == '~' ? $product->get() : $product->paginate($limit, ['*'], 'page', $page));
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function performance_benchmark($id)
    {
        try
        {
            $json = Storage::disk('local')->exists('generate/benchmark.json') ? json_decode(Storage::disk('local')->get('generate/benchmark.json'), true) : [];
            return $this->app_response('Product Performance', $this->product_price($json, $id));
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

     public function history_performance($id)
    {
        $product    = Product::select('m_products.product_id', 'm_products.product_name', 'b.price', 'b.return_1day', 'b.return_3day', 'b.return_1month', 'b.return_3month', 'b.return_6month', 'b.return_1year','b.return_3year','b.return_5year', 'c.asset_class_name', 'c.asset_class_color', 'd.asset_category_name', 'e.issuer_name', 'e.issuer_logo', 'f.profile_name')
                    ->join('m_products_period as b', 'm_products.product_id', '=', 'b.product_id')
                    ->leftJoin('m_asset_class as c', function($qry) { $qry->on('m_products.asset_class_id', '=', 'c.asset_class_id')->where('b.is_active', 'Yes'); })
                    ->leftJoin('m_asset_categories as d', function($qry) { $qry->on('c.asset_category_id', '=', 'd.asset_category_id')->where('d.is_active', 'Yes'); })
                    ->leftJoin('m_issuer as e', function($qry) { $qry->on('m_products.issuer_id', '=', 'e.issuer_id')->where('e.is_active', 'Yes'); })
                    ->leftJoin('m_risk_profiles as f', function($qry) { $qry->on('m_products.profile_id', '=', 'f.profile_id')->where('f.is_active', 'Yes'); })
                    ->where([['m_products.product_id', $id], ['m_products.is_active', 'Yes'], ['b.is_active', 'Yes']])
                    ->first();
         return $this->app_response('History Product Performance', $product);    
    }

    public function product_score_performance($id)
    {
        $product_score    = Product::select('m_products.product_name', 'b.asset_class_name', 'e.expected_return_year', 'e.standard_deviation')
                ->leftJoin('m_asset_class as b', function($qry) { $qry->on('m_products.asset_class_id', '=', 'b.asset_class_id')->where('b.is_active', 'Yes'); })
                ->leftJoin('t_products_scores as e', function($qry) { $qry->on('m_products.product_id', '=', 'e.product_id')->where('e.is_active', 'Yes'); })
                ->leftJoin('m_products_prices as h', function($qry) { $qry->on('m_products.product_id', '=', 'h.product_id')->where('h.is_active', 'Yes'); })
                ->where([['m_products.product_id', $id], ['m_products.is_active', 'Yes']])
                ->first();
         return $this->app_response('Product Score Performance', $product_score);  
    }

    public function performance_detail(Request $request)
    {
        try
        {
            $json       = Storage::disk('local')->exists('generate/price.json') ? json_decode(Storage::disk('local')->get('generate/price.json'), true) : [];
            $product    = Product::select('m_products.*', 'b.asset_class_name', 'c.issuer_name', 'c.issuer_logo', 'd.profile_name', 'e.expected_return_year', 'f.return_1day', 'f.price', 'g.asset_category_name')
                        ->leftJoin('m_asset_class as b', function($qry) { $qry->on('m_products.asset_class_id', '=', 'b.asset_class_id')->where('b.is_active', 'Yes'); })
                        ->leftJoin('m_issuer as c', function($qry) { $qry->on('m_products.issuer_id', '=', 'c.issuer_id')->where('c.is_active', 'Yes'); })
                        ->leftJoin('m_risk_profiles as d', function($qry) { $qry->on('m_products.profile_id', '=', 'd.profile_id')->where('d.is_active', 'Yes'); })
                        ->leftJoin('t_products_scores as e', function($qry) { $qry->on('m_products.product_id', '=', 'e.product_id')->where('e.is_active', 'Yes'); })
                        ->leftJoin('m_products_period as f', function($qry) { $qry->on('m_products.product_id', '=', 'f.product_id')->where('f.is_active', 'Yes'); })
                        ->leftJoin('m_asset_categories as g', function($qry) { $qry->on('b.asset_category_id', '=', 'g.asset_category_id')->where('g.is_active', 'Yes'); })
                        ->where([['m_products.product_id', $request->product_id], ['m_products.is_active', 'Yes']])
                        ->first();
            $price      = !empty($product->product_id) ? Price::where([['product_id', $product->product_id], ['is_active', 'Yes']])->orderBy('price_date', 'desc')->first() : [];
            $document   = !empty($product->product_id) ? DocumentView::select('document_id as id', 'asset_document_name as name', 'document_link as link')->where('product_id', $product->product_id)->get() : [];
            $perform    = array_merge(json_decode($product, true), ['document' => $document, 'price' => !empty($price->price_id) ? ['date' => $price->price_date, 'value' => $price->price_value] : [], 'product_price' => $this->product_price($json, $product->product_id)]);
            
            return $this->app_response('Product Performance', $perform);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function product_detail($id)
    {
        try
        {
            $data = Product::select('product_name', 'asset_class_name', 'issuer_logo')
                    ->leftJoin('m_asset_class as b', function($qry) { return $qry->on('m_products.asset_class_id', '=', 'b.asset_class_id')->where('b.is_active', 'Yes'); })
                    ->leftJoin('m_issuer as c', function($qry) { return $qry->on('m_products.issuer_id', '=', 'c.issuer_id')->where('c.is_active', 'Yes'); })
                    ->where([['product_id', $id], ['m_products.is_active', 'Yes']]);
            return $this->app_response('Product', $data->first());
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    private function product_price($json, $id=null)
    {
        $data = [];
        if (!empty($id) && isset($json[$id]))
        {
            foreach ($json[$id] as $key => $val)
            {
                $data[] = [strtotime('+1 day ' . $key)*1000, floatval($val)];
            }
        }
        return $data;
    }

    public function product_recomendation(Request $request)
    {
        try
        {
            $latestPrice = DB::table('m_products_prices as mpp')
                            ->where('mpp.is_active', 'Yes')
                            ->select('mpp.product_id', DB::raw('MAX(mpp.price_date) as price_date'))
                            ->groupBy('mpp.product_id');

            $subquery = DB::table('m_products as mp')
                    ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                    ->join('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
                    ->join('m_products_prices as mpp', 'mpp.product_id', 'mp.product_id')
                    ->joinSub($latestPrice, 'latest_price', function($join) {
                        $join->on('mpp.product_id', '=', 'latest_price.product_id')
                            ->on('mpp.price_date', '=', 'latest_price.price_date');
                    })
                    ->join('t_products_scores as tps', 'tps.product_id', 'mp.product_id')
                    ->leftJoin('m_issuer as mi', function($join) { $join->on('mi.issuer_id', 'mp.issuer_id')->where('mi.is_active', 'Yes'); })
                    ->leftJoin('m_currency as mc', function($join) { $join->on('mc.currency_id', 'mp.currency_id')->where('mc.is_active', 'Yes'); })
                    ->whereNotIn('mact.asset_category_name', ['Index', 'Benchmark', 'Index/Benchmark'])
                    ->where([['mp.is_active', 'Yes'], ['mpp.is_active', 'Yes'], ['tps.is_active', 'Yes'], ['mac.is_active', 'Yes'], 
                            ['mact.is_active', 'Yes']])
                    ->select('mp.product_id', 'mp.product_name', 'mi.issuer_logo', 'mpp.price_value', 'mc.symbol as currency_symbol', 
                            'tps.expected_return_year', 'mac.asset_class_name',
                            DB::raw('ROW_NUMBER() OVER(PARTITION BY mac.asset_class_name ORDER BY mpp.price_date DESC, tps.sharpe_ratio DESC) AS rn'));
            
            $recom = \DB::table(DB::raw("({$subquery->toSql()}) as products"))
                ->mergeBindings($subquery)
                ->where('products.rn', 1)
                ->select('*')
                ->get();
                
            return $this->app_response('Product Recommendation', $recom);  
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function save(Request $request, $id = null)
    {
        $error_check = false;
        foreach (['allow_new_sub', 'allow_switching', 'allow_topup', 'allow_sip', 'allow_redeem'] as $allow)
            $request->request->add([$allow => $request->input($allow) == 'true' ? 't' : 'f']);

        foreach (['min_buy', 'max_buy', 'min_sell', 'max_sell', 'min_switch_out','max_switch_out','min_switch_in','max_switch_in','multiple_purchase'] as $not_allow_minus) {

            if((float) $request->input($not_allow_minus) < 0) {
                 $error_check = true;
            }

        }

        if(!$error_check) {
           return $this->db_save($request, $id);            
        } else {
           $error = ['error_code' => 422, 'error_msg' => 'Suspected of intercepting action, please check in group Transaction Rules not smaller than 0'];      

           return $this->app_response('validation save', '', $error);
        }
    }
    
    public function total_product()
    {
        try
        {
            return $this->app_response('Total Product', Product::where('is_active', 'Yes')->count());
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function ws_data(Request $request)
    {
        try
        {
            $insert = $update = 0;
            $data   = [];
            $api = $this->api_ws(['sn' => 'Product'])->original['data'];
            foreach ($api as $a)
            {                
                $qry    = Product::where([['ext_code', $a->code], ['is_active', 'Yes']])->first();
                $id     = !empty($qry->product_id) ? $qry->product_id : null;
                $request->request->add([
                    'issuer_id'             => !empty($a->issuerCode) ? $this->db_row('issuer_id', ['where' => [['ext_code', $a->issuerCode]]], 'SA\Assets\Products\Issuer')->original['data'] : null,
                    'asset_class_id'        => !empty($a->assetClassCode) ? $this->db_row('asset_class_id', ['where' => [['asset_class_code', $a->assetClassCode]]], 'SA\Assets\AssetClass')->original['data'] : null,
                    'currency_id'           => !empty($a->currencyCode) ? $this->db_row('currency_id', ['where' => [['currency_code', $a->currencyCode]]], 'SA\Assets\Products\Currency')->original['data'] : null,
                    'thirdparty_id'         => !empty($a->thirdPartyCode) ? $this->db_row('thirdparty_id', ['where' => [['ext_code', $a->thirdPartyCode]]], 'SA\Assets\Products\ThirdParty')->original['data'] : null,
                    'profile_id'            => !empty($a->profileId) ? $this->db_row('profile_id', ['where' => [['ext_code', $a->profileId]]], 'SA\Reference\KYC\RiskProfiles\Profile')->original['data'] : null,
                    'product_name'          => $a->name,
                    'product_code'          => !empty($id) ? $qry->product_code : $a->code,
                    'product_type'          => $a->type,
                    'offering_period_start' => $a->offeringPeriodStart,
                    'offering_period_end'   => $a->offeringPeriodEnd,
                    'exit_windows_start'    => $a->exitWindowsStart,
                    'exit_windows_end'      => $a->exitWindowsEnd,
                    'maturity_date'         => $a->maturityDate,
                    'launch_date'           => $a->launchingDate,
                    'allow_new_sub'         => $a->allowNewSubs,
                    'allow_redeem'          => $a->allowRedeem,
                    'allow_switching'       => $a->allowSwitching,
                    'allow_topup'           => $a->allowTopUp,
                    'allow_sip'             => $a->allowSIP,
                    'min_buy'               => $a->minimumBuy,
                    'max_buy'               => $a->maximumBuy,
                    'min_sell'              => !empty($a->minimumSell) ?  $a->minimumSell : $a->minimumSellUnit,
		    'max_sell'              => !empty($a->maximumSell) ? $a->maximumSell : $a->maximumSellUnit,                    
		    'multiple_purchase'     => $a->multiplier,
                    'ext_code'              => $a->code,
                    'is_data'               => !empty($id) ? $qry->is_data : 'WS',
                    '__update'              => !empty($id) ? 'Yes' : ''
                ]);
                $this->db_save($request, $id, ['validate' => true]);

                if (empty($id))
                    $insert++;
                else
                    $update++;
            }
            return $this->app_partials($insert+$update, 0, ['save' => ['insert' => $insert, 'update' => $update]]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}