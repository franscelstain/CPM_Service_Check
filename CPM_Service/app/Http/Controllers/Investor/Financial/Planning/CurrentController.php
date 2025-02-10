<?php

namespace App\Http\Controllers\Investor\Financial\Planning;

use App\Http\Controllers\AppController;
use App\Models\Investor\Financial\Plannig\Current\Outstanding;
use Illuminate\Http\Request;
use Auth;

class CurrentController extends AppController
{
    public $table = 'Investor\Financial\Plannig\Current\Outstanding';
    
    public function index(Request $request)
    {
        try
        {
            $data   = Outstanding::select('t_assets_outstanding.*', 'c.product_name', 'c.product_code', 'd.asset_class_id', 'd.asset_class_name', 'd.asset_class_color', 'e.issuer_logo')
                    ->join('u_investors as b', 't_assets_outstanding.investor_id', '=', 'b.investor_id')
                    ->join('m_products as c', 't_assets_outstanding.product_id', '=', 'c.product_id')
                    ->leftJoin('m_asset_class as d', function($qry) { $qry->on('c.asset_class_id', '=', 'd.asset_class_id')->where('d.is_active', 'Yes'); })
                    ->leftJoin('m_issuer as e', function($qry) { $qry->on('c.issuer_id', '=', 'e.issuer_id')->where('e.is_active', 'Yes'); })
                    ->where([['t_assets_outstanding.investor_id', Auth::id()], ['t_assets_outstanding.outstanding_date', $this->latest_date()], ['t_assets_outstanding.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']]);                 
		
            if (!empty($request->search))
                $data = $data->where('c.product_name', 'ilike', '%'. $request->search .'%');
            if (!empty($request->asset_class_id))
                $data = $data->where('d.asset_class_id', $request->asset_class_id);
            if (!empty($request->balance_minimum))
                $data = $data->where('balance_amount', '>=', $request->balance_minimum);
            if (!empty($request->balance_maximum))
                $data = $data->where('balance_amount', '<=', $request->balance_maximum);
            
            $data   = $data->distinct()->get();
            $total  = !empty($request->total) ? ['total' => $this->list_total($data)] : [];
            
            return $this->app_response('Current Portfolio', array_merge(['data' => $data], $total));
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    private function latest_date()
    {
        $latest = Outstanding::where([['investor_id', Auth::id()], ['is_active', 'Yes']])->max('outstanding_date');
        return !empty($latest) ? $latest : $this->app_date();
    }

    private function list_total($item)
    {
        $asset      = $account = $product = [];
        $balance    = $earning = $return = 0;
        foreach ($item as $i) 
        {
            if (in_array($i->asset_class_id, array_keys($asset)))
            {
                $asset[$i->asset_class_id]['amount'] += $i->balance_amount;
            }
            else
            {
                $asset[$i->asset_class_id] = ['name' => $i->asset_class_name, 'amount' => floatval($i->balance_amount), 'color' => $i->asset_class_color];
            }
            $product[] = ['product_name' => $i->product_name, 'balance_amount' => $i->balance_amount, 'account_no' => $i->account_no];
            $account[] = $i->account_no;
            $balance += $i->balance_amount;
            $earning += $i->return_amount;
        }
        return ['account_no' => count($account), 'asset' => array_values($asset), 'balance' => $balance, 'earning' => $earning, 'product' => $product, 'returns' => $return];
    }
    
    public function total_per_days(Request $request)
    {
        try
        {
            $res = [];
            $day = !empty($request->day) ? $request->day : 1;
            for ($i = 0; $i < $day; $i++)
            {
                 $date   = date('Y-m-d', strtotime('-'.$i.' days'));
                $data   = Outstanding::select('t_assets_outstanding.product_id', 't_assets_outstanding.account_no', 't_assets_outstanding.balance_amount', 't_assets_outstanding.return_amount','c.product_name', 'd.asset_class_id', 'd.asset_class_name', 'd.asset_class_color')
                        ->join('u_investors as b', 't_assets_outstanding.investor_id', '=', 'b.investor_id')
                        ->join('m_products as c', 't_assets_outstanding.product_id', '=', 'c.product_id')
                        ->leftJoin('m_asset_class as d', function($qry) { $qry->on('c.asset_class_id', '=', 'd.asset_class_id')->where('d.is_active', 'Yes'); })
                        ->where([['t_assets_outstanding.investor_id', Auth::id()], ['t_assets_outstanding.outstanding_date', $date], ['t_assets_outstanding.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                        ->distinct()
			->get();
			
                $res[$date] = $this->list_total($data);
            }
            ksort($res);
            return $this->app_response('AUM Current', $res);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}
