<?php

namespace App\Http\Controllers\Investor\Financial\Condition;

use App\Models\Investor\Financial\Condition\AssetLiability;
use App\Http\Controllers\AppController;
use Illuminate\Http\Request;
use Auth;

class AssetsLiabilitiesController extends AppController
{
    public $table = 'Investor\Financial\Condition\AssetLiability';

    public function index(Request $request)
    {
        try
        {
            $type   = is_array($request->type) ? $request->type : [$request->type];
            $data   = [];
            $qry    = AssetLiability::select('t_assets_liabilities.*', 'b.financial_name')
                    ->join('m_financials as b', 't_assets_liabilities.financial_id', '=', 'b.financial_id')
                    ->where([['t_assets_liabilities.investor_id', Auth::id()], ['t_assets_liabilities.is_active', 'Yes'], ['b.is_active', 'Yes']])
                    ->whereIn('b.financial_type', $type)
                    ->orderBy('b.sequence_to', 'asc')->get();
            foreach ($qry as $q)
            {
                $data[$q->financial_name][] = [
                    'transaction_id'    => $q->transaction_id,
                    'transaction_name'  => $q->transaction_name,
                    'amount'            => $q->amount, 
                    'updated_at'        => $q->updated_at
                ];
            }
            return $this->app_response('Financial', ['list' => $data]);
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

    public function save(Request $request, $id = null)
    {
        $request->request->add(['investor_id' => Auth::id()]);
        return $this->db_save($request, $id);
    }
    
    public function total(Request $request)
    {
        try
        {
            $sum    = [];
            $total  = 0;
            $typ    = $request->input('type');
            foreach ($typ as $t)
            {
                if ($t != end($typ))
                {
                    $res    = AssetLiability::join('m_financials as b', 't_assets_liabilities.financial_id', '=', 'b.financial_id')
                            ->where([['investor_id', Auth::id()], ['b.financial_type', ucwords($t)], ['t_assets_liabilities.is_active', 'Yes'], ['b.is_active', 'Yes']])
                            ->sum('amount');
                    $total  = $total == 0 ? $res : $total - $res;
                }
                $sum[$t] = $t == end($typ) ? $total : $res;
            }
            return $this->app_response('Total Assets & Liabilities', $sum);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function totalByName(Request $request)
    {
        try
        {
            $name = $request->input('name');
            $typ  = $request->input('type');
            $sum  = AssetLiability::join('m_financials as b', 't_assets_liabilities.financial_id', '=', 'b.financial_id')
                    ->where([['investor_id', Auth::id()], ['b.financial_type', $typ], ['b.financial_name', $name], ['t_assets_liabilities.is_active', 'Yes'], ['b.is_active', 'Yes']])
                    ->sum('amount');
            return $this->app_response('Total', $sum);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}