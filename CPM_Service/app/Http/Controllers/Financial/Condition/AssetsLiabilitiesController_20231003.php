<?php

namespace App\Http\Controllers\Financial\Condition;

use App\Http\Controllers\AppController;
use App\Models\Financial\AssetOutstanding;
use App\Models\Financial\Condition\AssetLiability;
use App\Models\Financial\LiabilityOutstanding;
use App\Models\Financial\Condition\IncomeExpense;
use App\Models\Users\Investor\Investor;
use App\Traits\Financial\Condition\FinancialRatio;
use Illuminate\Http\Request;
use Auth;

class AssetsLiabilitiesController extends AppController
{
    use FinancialRatio;
    
    public $table = 'Financial\Condition\AssetLiability';

    public function index(Request $request)
    {
        return $this->findata($request, Auth::id());
	}
	
	private function assets_outstanding($type, $inv_id, $select='', $where=[])
	{
		$select = !empty($select) ? $select : "outstanding_id, concat(product_name, ' - ', account_no) as outstanding_name, balance_amount as amount, t_assets_outstanding.updated_at, m_financials.financial_name, m_financials.financial_id, cast('1' as integer) as t";
		$qry  = AssetOutstanding::selectRaw($select)
                ->join('m_products as b', 't_assets_outstanding.product_id', '=', 'b.product_id')
                ->join('m_asset_class as c', 'b.asset_class_id', '=', 'c.asset_class_id')
                ->join('m_financials_assets as d', 'c.asset_class_id', '=', 'd.asset_class_id')
                ->join('m_financials', 'd.financial_id', '=', 'm_financials.financial_id')
                ->where([['t_assets_outstanding.investor_id', $inv_id], ['t_assets_outstanding.is_active', 'Yes'], ['b.is_active', 'Yes']])
                ->where([['c.is_active', 'Yes'], ['d.is_active', 'Yes'], ['m_financials.is_active', 'Yes'], ['m_financials.financial_type', $type]])
                ->where(array_merge([['t_assets_outstanding.outstanding_date', $this->app_date()]], $where));
		return $qry;
	}

    public function detail($id)
    {
        return $this->db_detail($id);
    }
	
	private function findata($request, $inv_id)
	{
		try
        {
            $type	= ucwords(strtolower($request->type));
			$data	= [];
			$fin	= $this->fin_qry($type, $inv_id);
			$qry    = $fin->qry->selectRaw("transaction_id, transaction_name, amount, t_assets_liabilities.updated_at, m_financials.financial_name, m_financials.financial_id, cast('0' as integer) as t")
					->union($fin->union)
					->get();
			$upd_at	= '';
            foreach ($qry as $q)
            {
				$upd_at = empty($upd_at) || strtotime($q->updated_at) > strtotime($upd_at) ? $q->updated_at : $upd_at;
				$data[$q->financial_name][] = [
					'transaction_id'    => $q->transaction_id,
					'transaction_name'  => $q->transaction_name,
					'amount'            => $q->amount, 
					'updated_at'        => $upd_at,
					't'       			=> $q->t,
				];
			}
            return $this->app_response('Financial', ['list' => $data]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
	}

    public function financial_score(Request $request)
    {
        try
        {
            $inv    = [];
            $data   = Investor::where([['sales_id', $this->auth_user()->id], ['is_active', 'Yes']])->get();
            foreach ($data as $dt)
            {
                $ie     = $this->total_income_expense($request, $dt->investor_id, ['income', 'expense', 'cash_flow'], true);
                $al     = $this->total_assets_liabilities($request, $dt->investor_id, ['assets', 'liabilities', 'networth'], true);
                $inv[]  = [
                    'investor_id'   => $dt->investor_id,
                    'cif'           => $dt->cif,
                    'fullname'      => $dt->fullname,
                    'photo_profile' => $dt->photo_profile,
                    'cashflow'      => $ie->cash_flow,
                    'networth'      => $al->networth,
                    'ratio'         => $this->ratio(['income-expense' => $ie, 'assets-liabilities' => $al], $dt, 'Y')
                ];
            }
            return $this->app_response('Financial Score', $inv);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
	
	public function investor(Request $request)
    {
        try
        {
            $limit  = !empty($request->limit) ? $request->limit : 10;
            $page   = !empty($request->page) ? $request->page : 1;
            $offset = ($page-1)*$limit;
            $data	= Investor::where([['sales_id', $this->auth_user()->id], ['is_active', 'Yes']]);
            $inv 	= [];

            if (!empty($request->search))
            {
               $data  = $data->where(function($qry) use ($request) {
                            $qry->where('u_investors.fullname', 'ilike', '%'. $request->search .'%')
                                ->orWhere('u_investors.cif', 'ilike', '%'. $request->search .'%');
                        });
            } 
    		foreach ($data->get() as $dt) 
    		{
    			$fin_asset 			= $this->fin_qry('Assets', $dt->investor_id, 'balance_amount as amount');
    			$total_asset		= $fin_asset->qry->selectRaw('amount')->union($fin_asset->union)->sum('amount');
    			$fin_liability 		= $this->fin_qry('Liabilities', $dt->investor_id, 'outstanding_balance as amount');
    			$total_liability	= $fin_liability->qry->selectRaw('amount')->union($fin_liability->union)->sum('amount');

                $inv[] = [
                    'investor_id'   => $dt->investor_id,
                    'cif'           => $dt->cif,
                    'fullname'      => $dt->fullname,
                    'photo_profile' => $dt->photo_profile,
                    'networth'      => $total_asset - $total_liability
                ];
            }

            $total = $data->count();
            $total_data = $page*$limit;
            $paginate = [
                'current_page'  => $page,
                'data'          => $inv,
                'from'          => $page > 1 ?  1 + (($page-1) * $limit) : 1,
                'per_page'      => $limit,
                'to'            => $total_data >= $total ? $total : $total_data,
                'total'         => $total
            ]; 
            
            return $this->app_response('Investor - Assets & Liabilities', $paginate); 
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }  
    }

    private function total_amount($inv_id, $fin_typ)
    {
        $total  = 0;
        $data   = IncomeExpense::select('period_of_time', 'amount')
                ->join('m_financials as b', 't_income_expense.financial_id', '=', 'b.financial_id')
                ->where([['investor_id', $inv_id], ['financial_type', $fin_typ], ['t_income_expense.is_active', 'Yes'], ['b.is_active', 'Yes']])
                ->get();
        foreach ($data as $dt)
        {
            $total += $dt->period_of_time == 'Yearly' ? $dt->amount : $dt->amount * 12;
        }
        return $total;
    }

	private function liability_outstanding($inv_id, $select='')
	{
		$select	= !empty($select) ? $select : "liabilities_outstanding_id, account_id, outstanding_balance, updated_at, liabilities_name, cast('0' as integer) as fin_id, cast('1' as integer) as t";
		$qry	= LiabilityOutstanding::selectRaw($select)
				->where([['investor_id', $inv_id],['is_active', 'Yes'], ['outstanding_date', $this->app_date()]]);
		return $qry;
	}

    public function list_for_sales(Request $request)
    {
        return $this->findata($request, $request->investor_id);
    }

    public function save(Request $request, $id = null)
    {
        $request->request->add(['investor_id' => Auth::id()]);
        return $this->db_save($request, $id);
    }
    
    public function total(Request $request)
    {
        return $this->total_assets_liabilities($request, Auth::id());
    }
    
    public function total_for_sales(Request $request, $inv_id)
    {
        return $this->total_assets_liabilities($request, $inv_id);
    }
    
    public function totalByName(Request $request)
    {
        return $this->total_with_name(Auth::id(), $request->input('name'), $request->input('type'));
    }

    public function total_for_sales_with_name(Request $request, $inv_id)
    {
        return $this->total_with_name($inv_id, $request->input('name'), $request->input('type'));
    }
}