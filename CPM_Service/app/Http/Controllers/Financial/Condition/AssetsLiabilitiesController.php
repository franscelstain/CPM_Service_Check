<?php

namespace App\Http\Controllers\Financial\Condition;

use App\Http\Controllers\AppController;
use App\Interfaces\Finance\FinancialRepositoryInterface;
use App\Interfaces\Users\InvestorRepositoryInterface;
use App\Models\Financial\AssetOutstanding;
use App\Models\Financial\Condition\AssetLiability;
use App\Models\Financial\LiabilityOutstanding;
use App\Models\Financial\Condition\IncomeExpense;
use App\Models\Users\Investor\Investor;
use App\Services\Handlers\Finance\FinancialRatioService;
use App\Traits\Financial\Condition\FinancialRatio;
use Illuminate\Http\Request;
use Auth;
use DB;

class AssetsLiabilitiesController extends AppController
{
    use FinancialRatio;
    
    public $table = 'Financial\Condition\AssetLiability';
    protected $financialRepository;
    protected $investorRepository;
    protected $ratioService;

    public function __construct(
        FinancialRepositoryInterface $financialRepository, 
        InvestorRepositoryInterface $investorRepository,
        FinancialRatioService $ratioService
    ){
        $this->financialRepository = $financialRepository;
        $this->investorRepository = $investorRepository;
        $this->ratioService = $ratioService;
    }

    public function index(Request $request)
    {
        return $this->findata($request, Auth::id());
	}
	
	private function assets_outstanding($type, $inv_id, $select='', $where=[])
	{
		$select = !empty($select) ? 
            $select : 
            "tao.outstanding_id, 
              concat(mp.product_name, ' - ', tao.account_no) as outstanding_name, 
              tao.balance_amount as amount, 
              tao.updated_at, 
              mf.financial_name, 
              mf.financial_id, 
              cast('1' as integer) as t";
		
        $qry = DB::table(DB::raw("(
                    SELECT 
                        tao.*, 
                        ROW_NUMBER() OVER (
                            PARTITION BY tao.product_id, tao.account_no 
                            ORDER BY tao.data_date DESC, tao.outstanding_id DESC
                        ) AS rank
                    FROM t_assets_outstanding tao
                    WHERE tao.investor_id = {$inv_id}
                        AND tao.is_active = 'Yes'
                        AND tao.outstanding_date = '{$this->app_date()}'
                ) AS tao"))
                ->join('m_products as mp', 'tao.product_id', '=', 'mp.product_id')
                ->join('m_asset_class as mac', 'mp.asset_class_id', '=', 'mac.asset_class_id')
                ->join('m_financials_assets as mfa', 'mac.asset_class_id', '=', 'mfa.asset_class_id')
                ->join('m_financials as mf', 'mfa.financial_id', '=', 'mf.financial_id')
                ->where('mp.is_active', 'Yes')
                ->where('mac.is_active', 'Yes')
                ->where('mfa.is_active', 'Yes')
                ->where('mf.is_active', 'Yes')
                ->where(array_merge([['mf.financial_type', $type]], $where))
                ->where('tao.rank', 1)
                ->selectRaw($select);

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
			$qry    = $fin->qry->selectRaw("transaction_id, transaction_name, amount, t_assets_liabilities.updated_at, mf.financial_name, mf.financial_id, cast('0' as integer) as t")
					->union($fin->union)
                    ->distinct()->get();
            // return $qry;
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
            $query  = DB::table('u_investors')->where([['sales_id', $this->auth_user()->id], ['is_active', 'Yes']]);

            if (isset($request->limit) && $request->limit > 0)
                $investors = $query->limit($request->limit)->get();
            else
                $investors = $query->get();

            $investorId = $investors->pluck('investor_id');

            $income = DB::table('t_income_expense as tie')
                ->join('m_financials as mf', 'mf.financial_id', 'tie.financial_id')
                ->where([['mf.financial_type', 'Income'], ['tie.is_active', 'Yes'], ['mf.is_active', 'Yes']])
                ->whereIn('tie.investor_id', $investorId)
                ->select('tie.investor_id', DB::raw("SUM(CASE WHEN tie.period_of_time = 'Yearly' THEN tie.amount ELSE tie.amount * 12 END) as total"))
                ->groupBy('tie.investor_id')
                ->get();

            $expense = DB::table('t_income_expense as tie')
                ->join('m_financials as mf', 'mf.financial_id', 'tie.financial_id')
                ->where([['mf.financial_type', 'Expense'], ['tie.is_active', 'Yes'], ['mf.is_active', 'Yes']])
                ->whereIn('tie.investor_id', $investorId)
                ->select('tie.investor_id', DB::raw("SUM(CASE WHEN tie.period_of_time = 'Yearly' THEN tie.amount ELSE tie.amount * 12 END) as total"))
                ->groupBy('tie.investor_id')
                ->get();

            $assets = DB::table('t_assets_liabilities as tal')
                    ->join('m_financials as mf', 'mf.financial_id', 'tal.financial_id')
                    ->whereIn('tal.investor_id', $investorId)
                    ->where([['mf.financial_type', 'Assets'], ['tal.is_active', 'Yes'], ['mf.is_active', 'Yes']])
                    ->where('tal.amount', '>=', 1)
                    ->select('tal.investor_id', DB::raw("SUM(tal.amount) as total"))
                    ->groupBy('tal.investor_id')
                    ->get();

            $assetsAmount = DB::table('t_assets_outstanding as tao')
                ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
                ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                ->join('m_financials_assets as mfa', 'mfa.asset_class_id', 'mac.asset_class_id')
                ->join('m_financials as mf', 'mf.financial_id', 'mfa.financial_id')
                ->whereIn('tao.investor_id', $investorId)
                ->where([['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], ['mac.is_active', 'Yes'], 
                        ['mfa.is_active', 'Yes'], ['mf.is_active', 'Yes'], ['mf.financial_type', 'Assets'],
                        ['tao.outstanding_date', DB::raw('CURRENT_DATE')], ['tao.balance_amount', '>=', 1]])
                ->select('tao.investor_id', DB::raw("SUM(tao.balance_amount) as total"))
                ->groupBy('tao.investor_id');

            $liabilities = DB::table('t_assets_liabilities as tal')
                    ->join('m_financials as mf', 'mf.financial_id', 'tal.financial_id')
                    ->whereIn('tal.investor_id', $investorId)
                    ->where([['mf.financial_type', 'Liabilities'], ['tal.is_active', 'Yes'], ['mf.is_active', 'Yes']])
                    ->where('tal.amount', '>=', 1)
                    ->select('tal.investor_id', DB::raw("SUM(tal.amount) as total"))
                    ->groupBy('tal.investor_id')
                    ->get();
            
            $liabilitiesAmount = DB::table('t_liabilities_outstanding')
                    ->whereIn('investor_id', $investorId)
                    ->where([['is_active', 'Yes'], ['outstanding_date', DB::raw('CURRENT_DATE')]])
                    ->select('investor_id', DB::raw("SUM(outstanding_balance) as total"))
                    ->groupBy('investor_id');

            $data = $investors->map(function ($investor) use ($income, $expense, $assets, $assetsAmount, $liabilities, $liabilitiesAmount) {
                $incomeValue            = optional($income->where('investor_id', $investor->investor_id)->first())->total ?? 0;
                $expenseValue           = optional($expense->where('investor_id', $investor->investor_id)->first())->total ?? 0;
                $cashflow               = $incomeValue - $expenseValue;
                $incomeExpense          = (object) ['income' => $incomeValue, 'expense' => $expenseValue, 'cashflow' => $cashflow];
                    
                $assetValue             = optional($assets->where('investor_id', $investor->investor_id)->first())->total ?? 0;
                $assetAmountValue       = optional($assetsAmount->where('investor_id', $investor->investor_id)->first())->total ?? 0;
                $assetTotal             = $assetValue + $assetAmountValue;
                    
                $liabilityValue         = optional($liabilities->where('investor_id', $investor->investor_id)->first())->total ?? 0;
                $liabilityAmountValue   = optional($liabilitiesAmount->where('investor_id', $investor->investor_id)->first())->total ?? 0;
                $liabilityTotal         = $liabilityValue + $liabilityAmountValue;
                    
                $networth               = $assetTotal - $liabilityTotal;
                $assetsLiab             = (object) ['assets' => $assetTotal, 'liabilities' => $liabilityTotal, 'networth' => $networth];
                    
                return [
                    'investor_id'   => $investor->investor_id,
                    'cif'           => $investor->cif,
                    'fullname'      => $investor->fullname,
                    'photo_profile' => $investor->photo_profile,
                    'cashflow'      => $cashflow,
                    'networth'      => $networth,
                    'ratio'         => $this->ratio(['income-expense' => $incomeExpense, 'assets-liabilities' => $assetsLiab], $investor, 'Y')
                ];
            });                    
            
            return $this->app_response('Financial Score', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function financial_score_data_old(Request $request)
    {
        try
        {
            $userId = $this->auth_user()->id;
            $total  = $this->investorRepository->countInvestorsBySales($userId);
            
            if ($total === 0) {
                return $this->app_response('Financial Score', [
                    'draw' => $request->draw ?? 1,
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => []
                ]);
            }

            $search     = $request->search['value'] ?? '';
            $start      = $request->start ?? 0;
            $length     = $request->length ?? 10;
            $colName    = $request->columns[$request->order[0]['column']]['data'] ?? 'fullname';
            $colSort    = $request->order[0]['dir'] ?? 'asc';

            $investors = $this->investorRepository->getInvestorsBySalesWithPagination($userId, $search, $start, $length, $colName, $colSort);
            $totalFiltered = !empty($search) ? count($investors) : $total;

            $investorIds = $investors->pluck('investor_id')->toArray();

            $incomeData             = $this->financialRepository->getIncomeByInvestorId($investorIds);
            $expenseData            = $this->financialRepository->getExpenseByInvestorId($investorIds);
            $assetsData             = $this->financialRepository->getAssetsByInvestorId($investorIds);
            $assetsAmountData       = $this->financialRepository->getAssetsAmountByInvestorId($investorIds);
            $liabilitiesData        = $this->financialRepository->getLiabilitiesByInvestorId($investorIds);
            $liabilitiesAmountData  = $this->financialRepository->getLiabilitiesAmountByInvestorId($investorIds);            
            
            $items = $investors->map(function ($investor) use ($incomeData, $expenseData, $assetsData, $assetsAmountData, $liabilitiesData, $liabilitiesAmountData) {
                $incomeValue            = optional($incomeData->where('investor_id', $investor->investor_id)->first())->total ?? 0;
                $expenseValue           = optional($expenseData->where('investor_id', $investor->investor_id)->first())->total ?? 0;
                $cashflow               = $incomeValue - $expenseValue;
                $incomeExpense          = (object) ['income' => $incomeValue, 'expense' => $expenseValue, 'cashflow' => $cashflow];

                $assetValue             = optional($assetsData->where('investor_id', $investor->investor_id)->first())->total ?? 0;
                $assetAmountValue       = optional($assetsAmountData->where('investor_id', $investor->investor_id)->first())->total ?? 0;
                $assetTotal             = $assetValue + $assetAmountValue;
                    
                $liabilityValue         = optional($liabilitiesData->where('investor_id', $investor->investor_id)->first())->total ?? 0;
                $liabilityAmountValue   = optional($liabilitiesAmountData->where('investor_id', $investor->investor_id)->first())->total ?? 0;
                $liabilityTotal         = $liabilityValue + $liabilityAmountValue;
                    
                $networth               = $assetTotal - $liabilityTotal;
                $assetsLiab             = (object) ['assets' => $assetTotal, 'liabilities' => $liabilityTotal, 'networth' => $networth];
                    
                return [
                    'investor_id'   => $investor->investor_id,
                    'cif'           => $investor->cif,
                    'fullname'      => $investor->fullname,
                    'photo_profile' => $investor->photo_profile,
                    'cashflow'      => $cashflow,
                    'networth'      => $networth,
                    'ratio'         => $this->ratio(['income-expense' => $incomeExpense, 'assets-liabilities' => $assetsLiab], $investor, 'Y')
                ];
            });
            
            return $this->app_response('Financial Score', [
                'draw' => $request->draw ?? 1,
                'recordsTotal' => $total,
                'recordsFiltered' => $totalFiltered,            
                'data' => $items
            ]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function financial_score_data(Request $request)
    {
        try {
            $userId = $this->auth_user()->id;
            $total  = $this->investorRepository->countInvestorsBySales($userId);

            if ($total === 0) {
                return $this->app_response('Financial Score', [
                    'draw' => $request->draw ?? 1,
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => []
                ]);
            }

            $search     = $request->search['value'] ?? '';
            $start      = $request->start ?? 0;
            $length     = $request->length ?? 10;
            $colName    = $request->columns[$request->order[0]['column']]['data'] ?? 'fullname';
            $colSort    = $request->order[0]['dir'] ?? 'asc';

            $investors = $this->investorRepository->getInvestorsBySalesWithPagination($userId, $search, $start, $length, $colName, $colSort);
            $totalFiltered = !empty($search) ? count($investors) : $total;

            $investorIds = $investors->pluck('investor_id')->toArray();
            $summaryData = $this->financialRepository->getFinancialSummary($investorIds)->keyBy('investor_id');

            $items = $investors->map(function ($investor) use ($summaryData) {
                $summary = $summaryData[$investor->investor_id] ?? null;
                $data = [
                    'income' => $summary->income ?? 0,
                    'expense' => $summary->expense ?? 0,
                    'assets' => $summary->assets ?? 0,
                    'liabilities' => $summary->liabilities ?? 0,
                ];

                $networth = ($summary->assets ?? 0) - ($summary->liabilities ?? 0);
                $cashflow = ($summary->income ?? 0) + ($summary->expense ?? 0);
                $ratio = $this->ratioService->calculateFinancialScore($data, $investor);

                return [
                    'investor_id'   => $investor->investor_id,
                    'cif'           => $investor->cif,
                    'fullname'      => $investor->fullname,
                    'photo_profile' => $investor->photo_profile,
                    'cashflow'      => $cashflow,
                    'networth'      => $networth,
                    'ratio'         => $ratio
                ];
            });

            return $this->app_response('Financial Score', [
                'draw' => $request->draw ?? 1,
                'recordsTotal' => $total,
                'recordsFiltered' => $totalFiltered,
                'data' => $items
            ]);
        } catch (\Exception $e) {
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

	// private function liability_outstanding($inv_id, $select='')
	// {
	// 	$select	= !empty($select) ? $select : "liabilities_outstanding_id, account_id, outstanding_balance, updated_at, liabilities_name, cast('0' as integer) as fin_id, cast('1' as integer) as t";
	// 	$qry	= LiabilityOutstanding::selectRaw($select)
	// 			->where([['investor_id', $inv_id],['is_active', 'Yes'], ['outstanding_date', $this->app_date()]]);
	// 	return $qry;
	// }
    private function liability_outstanding($inv_id, $select='')
	{
        $select	= !empty($select) ? $select : "liabilities_outstanding_id, account_id, outstanding_balance, updated_at, liabilities_name, cast('0' as integer) as fin_id, cast('1' as integer) as t";
        $subQuery = DB::table('t_liabilities_outstanding as tlo')
            ->select(
                'tlo.investor_id',
                'tlo.liabilities_id', 
                'tlo.account_id', 
                DB::raw('MAX(tlo.data_date) as latest_data_date')
            )
            ->where([
                ['tlo.investor_id', $inv_id],
                ['tlo.is_active', 'Yes'],
                ['tlo.outstanding_date', $this->app_date()],
            ])
            ->groupBy('tlo.investor_id', 'tlo.account_id', 'tlo.liabilities_id');
        $qry = DB::table(DB::raw("(
                    SELECT 
                        tlo.*, 
                        ROW_NUMBER() OVER (
                            PARTITION BY tlo.investor_id, tlo.liabilities_id, tlo.account_id 
                            ORDER BY tlo.data_date DESC, tlo.liabilities_outstanding_id DESC
                        ) AS rank
                    FROM 
                        t_liabilities_outstanding tlo
                    INNER JOIN (" . $subQuery->toSql() . ") AS latest_data
                        ON tlo.investor_id = latest_data.investor_id
                        AND tlo.liabilities_id = latest_data.liabilities_id
                        AND tlo.account_id = latest_data.account_id
                        AND (tlo.data_date = latest_data.latest_data_date OR latest_data.latest_data_date IS NULL)
                    WHERE 
                        tlo.investor_id = " . $inv_id . "
                        AND tlo.is_active = 'Yes'
                        AND tlo.outstanding_date = CURRENT_DATE
                ) AS tlo"))
                ->mergeBindings($subQuery)
                ->where('tlo.rank', 1)
                ->selectRaw($select);
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
    
    public function total_for_sales(Request $request, $id)
    {
        return $this->total_assets_liabilities($request, $id);
    }
    
    public function totalByName(Request $request)
    {
        return $this->total_with_name(Auth::id(), $request->input('name'), $request->input('type'));
    }

    public function total_for_sales_with_name(Request $request, $id)
    {
        return $this->total_with_name($id, $request->input('name'), $request->input('type'));
    }
}