<?php

namespace App\Http\Controllers\Sales\Financial\Construction;

use App\Models\Financial\AssetOutstanding;
use App\Http\Controllers\AppController;
use App\Models\Users\Investor\Investor;
use Illuminate\Http\Request;


class CurrentController extends AppController
{

    public function index($id='')
    {
        $data = Investor::select()
                ->join('m_risk_profiles as c', 'c.profile_id', '=', 'u_investors.profile_id')
                ->where([['u_investors.sales_id', $this->auth_user()->id], ['u_investors.is_active', 'Yes']])
				->get();
		$inv = [];
        foreach ($data as $dt) {
            $inv[] =[
            	'investor_id'           => $dt->investor_id,
                'cif'           		=> $dt->cif,
                'fullName'      		=> $dt->fullname,
                'photo_profile' 		=> $dt->photo_profile,
                'sid'					=> $dt->sid,
                'profile_name'  		=> $dt->profile_name,
                'profile_expired_date' 	=> $dt->profile_expired_date,
                'balance_amount'		=> $this->outstanding($dt->investor_id)
            ];
        }
        return $this->app_response('investor', $inv);   
    }

    public function outstanding($investor_id)
    {
    	$qry = AssetOutstanding::select('balance_amount')
    		->where([['t_assets_outstanding.investor_id', $investor_id], ['t_assets_outstanding.is_active', 'Yes'], ['t_assets_outstanding.outstanding_date', $this->app_date()]])
    		->sum('balance_amount');

    	return $qry;
    }

    public function detail_current(Request $request, $investor_id)
    {
        try
        {
            $data   = [];
            $inv    = Investor::leftJoin('m_risk_profiles as b', function($qry) {$qry->on('u_investors.profile_id', '=', 'b.profile_id')->where('b.is_active', 'Yes'); })
                    ->where([['u_investors.investor_id', $investor_id], ['u_investors.sales_id', $this->auth_user()->id], ['u_investors.is_active', 'Yes']])->first();
            $inv_nm = !empty($inv->investor_id) ? $inv->fullname : '';
            $prf_nm = !empty($inv->investor_id) ? $inv->profile_name : '';
            
            if (!empty($inv->investor_id))
            {
                $data   = AssetOutstanding::select('t_assets_outstanding.*', 'c.product_name', 'c.product_code', 'd.asset_class_id', 'd.asset_class_name', 'd.asset_class_color', 'e.issuer_logo')
                        ->join('u_investors as b', 't_assets_outstanding.investor_id', '=', 'b.investor_id')
                        ->join('m_products as c', 't_assets_outstanding.product_id', '=', 'c.product_id')
                        ->leftJoin('m_asset_class as d', function($qry) { $qry->on('c.asset_class_id', '=', 'd.asset_class_id')->where('d.is_active', 'Yes'); })
                        ->leftJoin('m_issuer as e', function($qry) { $qry->on('c.issuer_id', '=', 'e.issuer_id')->where('e.is_active', 'Yes'); })
                        ->where([['t_assets_outstanding.investor_id',  $investor_id], ['t_assets_outstanding.outstanding_date',  $this->app_date()], ['t_assets_outstanding.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']]);                 

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
            
            return $this->app_response('Current Portfolio', array_merge(['data' => $data, 'profilename' =>$prf_nm, 'investor' => $inv_nm], $total));  
            }
                      
            // return $this->app_response('Current Portfolio', ['detail' => $data, 'investor' => $inv_nm, 'profilename' =>$prf_nm]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
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
}
