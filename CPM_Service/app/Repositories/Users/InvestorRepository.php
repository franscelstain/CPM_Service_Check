<?php

namespace App\Repositories\Users;

use App\Interfaces\Users\InvestorRepositoryInterface;
use App\Models\Auth\Investor as AuthInvestor;
use App\Models\Users\Investor\Investor;
use Auth;
use DB;
use Session;


class InvestorRepository implements InvestorRepositoryInterface
{
    public function detailInvestor($id)
    {
        try
        {
            $auth = Auth::id() ? Auth::user() : Auth::guard('admin')->user();
            $investor = DB::table('u_investors as ui')
                        ->leftJoin('m_risk_profiles as rp', function($join) { $join->on('rp.profile_id', 'ui.profile_id')->where('rp.is_active', 'Yes'); })
                        ->where([['ui.investor_id', $id], ['ui.is_active', 'Yes']])
                        ->select('ui.*', 'rp.profile_name');
            if ($auth->usercategory_name == 'Sales')
                $investor->where('sales_id', $auth->id);
            return $investor->first();
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    public function eStatement()
    {
        try
        {
            $s      = env('BLAST_CS_START', 28);
            $e      = env('BLAST_CS_END', 10);
            $start  = date('Y-m-'.$s);
            $end    = date('Y-m-'.$e);
            $now    = date('Y-m-d');

            if (strtotime($start) > strtotime($end))
                $end = date('Y-m-'.$e, strtotime('+1 month'));
            if (strtotime($start) > strtotime($end))
                $start = date('Y-m-'.$s, strtotime('-1 month'));

            if (strtotime($start) <= strtotime($now) && strtotime($end) >= strtotime($now))
            {
                $qry = DB::table('u_investors_email_blast')
                        ->where('send_type', 'cs')
                        ->whereBetween('send_date', [$start, $end])
                        ->select('investor_id');
                return AuthInvestor::whereNotNull('cif')
                                    ->whereNotNull('email')
                                    ->where('investors.is_active', 'Yes')
                                    ->whereNotIn('investors.investor_id', $qry)
                                    ->get();
            }
            return [];
        }
        catch (\Exception $e)
        {
            return (object) ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    public function listInvestor($request)
    {
        try
        {
            $order      = $request->order;
            $colIdx     = !empty($order[0]['column']) ? $order[0]['column'] : '';
            $colSort    = $request->sort ?? 'asc';
            $colName    = !empty($request->columns[$colIdx]) ? $request->columns[$colIdx]['data'] : 'fullname';
            $offset     = $request->start ?? 0;
            $limit      = $request->length ?? 1;
            $search     = !empty($request->search) ? $request->search['value'] : '';
            $total      = $totalFiltered = 0;
            $items      = [];

            if ($request->dest == 'admin')
                $sql = $this->listInvestorForAdmin($search);
            elseif ($request->dest == 'assets-liabilities')
                $sql = $this->listInvestorForAssetsLiabilities($search, $offset, $limit, $colName, $colSort);
            elseif ($request->dest == 'drop-fund')
                $sql = $this->listInvestorForDropFund($search, $offset, $limit, $colName, $colSort);
            elseif ($request->dest == 'income-expense')
                $sql = $this->listInvestorForIncomeExpense($search, $offset, $limit, $colName, $colSort);
            elseif ($request->dest == 'portfolio-current')
                $sql = $this->listInvestorForPortfolioCurrent($search, $offset, $limit, $colName, $colSort);
            elseif ($request->dest == 'portfolio-goals')
                $sql = $this->listInvestorForPortfolioGoals($search, $offset, $limit, $colName, $colSort);
            elseif ($request->dest == 'report')
                $sql = $this->listInvestorForReport($search);
            elseif ($request->dest == 'sales')
                $sql = $this->listInvestorForSales($search, $offset, $limit, $colName, $colSort);

            if (isset($sql) && empty($sql->errors))
            {
                if (!empty($sql->item))
                {
                    $items = $sql->item;
                }
                else
                {
                    $investors = $sql->query;

                    if (!empty($colName))
                        $investors->orderBy($colName, $colSort);

                    $items = $investors->offset($offset)->limit($limit)->get();
                }
                
                $totalFiltered = $sql->totalFiltered;
                $total = $sql->total;
            }            
            
            return [
                'draw' => $request->draw ?? 1,
                'recordsTotal' => $total,
                'recordsFiltered' => $totalFiltered,            
                'data' => $items
            ];
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    private function listInvestorForAdmin($search)
    {
        try
        {
            $investors  = Investor::where('is_active', 'Yes');
            $total      = $investors->count();

            if (!empty($search))
            {
                $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
                $investors->where(function($qry) use ($search, $like) {
                    $qry->where('fullname', $like, '%'. $search .'%')
                        ->orWhere('email', $like, '%'. $search .'%')
                        ->orWhere('cif', $like, '%'. $search .'%');
                });
                $totFiltered = $investors->count();
            }

            return (object) [
                'query' => $investors,
                'total' => $total,
                'totalFiltered' => $totFiltered ?? $total
            ];
            
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    private function listInvestorForAssetsLiabilities($search, $start, $length, $colName, $colSort)
    {
        try
        {
            $auth   = Auth::guard('admin')->user();
            $userId = $auth->id ?? '';
            $query  = DB::table('u_investors as ui')
                    ->where([['ui.is_active', 'Yes'], ['ui.sales_id', $userId]])
                    ->select('ui.investor_id', 'ui.fullname', 'ui.photo_profile', 'ui.cif');
            $total  = $query->count();

            if (!empty($query))
            {
                $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
                $query->where(function($qry) use ($search, $like) {
                    $qry->where('ui.fullname', $like, '%'. $search .'%')
                        ->orWhere('ui.cif', $like, '%'. $search .'%');
                });
                $totFilter = $query->count();
            }

            if (!empty($colName))
                $query->orderBy($colName, $colSort);

            $investors = $query->skip($start)->take($length)->get();
            
            $investorIds = $investors->pluck('investor_id');
            
            $assets = DB::table('t_assets_liabilities as tal')
                    ->join('m_financials as mf', 'tal.financial_id', '=', 'mf.financial_id')
                    ->whereIn('tal.investor_id', $investorIds)
                    ->where('tal.is_active', 'Yes')
                    ->where('mf.is_active', 'Yes')
                    ->where('mf.financial_type', 'Assets')
                    ->select('tal.investor_id', DB::raw('SUM(tal.amount) as total_assets'))
                    ->groupBy('tal.investor_id')
                    ->get();

            $outstandingAmounts = DB::table('t_assets_outstanding as ao')
                ->whereIn('ao.investor_id', $investorIds)
                ->where('ao.outstanding_date', DB::raw('CURRENT_DATE'))
                ->where('ao.is_active', 'Yes')
                ->select('ao.investor_id', DB::raw('SUM(ao.balance_amount) as total_amount'))
                ->groupBy('ao.investor_id')
                ->get();
            
            $liabilities = DB::table('t_assets_liabilities as tal')
                ->join('m_financials as mf', 'tal.financial_id', '=', 'mf.financial_id')
                ->whereIn('tal.investor_id', $investorIds)
                ->where('tal.is_active', 'Yes')
                ->where('mf.is_active', 'Yes')
                ->where('mf.financial_type', 'Liabilities')
                ->select('tal.investor_id', DB::raw('SUM(tal.amount) as total_liabilities'))
                ->groupBy('tal.investor_id')
                ->get();

            $latestLiabilityOutstanding = DB::table('t_liabilities_outstanding')
                ->where('is_active', 'Yes')
                ->select('investor_id', DB::raw('MAX(outstanding_date) as latest_date'))
                ->groupBy('investor_id');

            $liabilitiesAmounts = DB::table('t_liabilities_outstanding as lo')
                ->whereIn('lo.investor_id', $investorIds)
                ->where('lo.outstanding_date', DB::raw('CURRENT_DATE'))
                ->where('lo.is_active', 'Yes')
                ->select('lo.investor_id', DB::raw('SUM(lo.outstanding_balance) as total_amount'))
                ->groupBy('lo.investor_id')
                ->get();

            $data = $investors->map(function ($investor) use ($assets, $outstandingAmounts, $liabilities, $liabilitiesAmounts) {
                $assetAmount        = $assets->where('investor_id', $investor->investor_id)->first()->total_assets ?? null;
                $latestAssetAmount  = $outstandingAmounts->where('investor_id', $investor->investor_id)->first()->total_amount ?? null;
                
                if ($assetAmount == null && $latestAssetAmount == null)
                {
                    $totalAssets = null;
                }
                else
                {
                    $assetAmount = $assetAmount ?? 0;
                    $latestAssetAmount = $latestAssetAmount ?? 0;
                    $totalAssets = $assetAmount + $latestAssetAmount;
                }
    
                $liabilityAmount        = $liabilities->where('investor_id', $investor->investor_id)->first()->total_liabilities ?? 0;
                $latestLiabilityAmount  = $liabilitiesAmounts->where('investor_id', $investor->investor_id)->first()->total_amount ?? 0;
                
                if ($liabilityAmount == null && $latestLiabilityAmount == null)
                {
                    $totalLiabilities = null;
                }
                else
                {
                    $liabilityAmount = $liabilityAmount ?? 0;
                    $latestLiabilityAmount = $latestLiabilityAmount ?? 0;
                    $totalLiabilities = $liabilityAmount + $latestLiabilityAmount;
                }

                if ($totalAssets == null && $totalLiabilities == null)
                {
                    $networth = $status = null;
                }
                else
                {
                    $totalAssets = $totalAssets ?? 0;
                    $totalLiabilities = $totalLiabilities ?? 0;
                    $networth = $totalAssets - $totalLiabilities;
                    $status = $networth >= 0 ? 'Good' : 'Bad';
                }                
    
                return [
                    'investor_id' => $investor->investor_id,
                    'cif' => $investor->cif,
                    'fullname' => $investor->fullname,
                    'photo_profile' => $investor->photo_profile,
                    'networth' => $networth,
                    'status' => $status
                ];
            });
            
            return (object) [
                'item' => $data,
                'total' => $total,
                'totalFiltered' => $totFilter ?? $total
            ];
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }   
    }

    private function listInvestorForDropFund($search, $start, $length, $colName, $colSort)
    {
        try
        {
            $auth   = Auth::guard('admin')->user();
            $userId = $auth->id ?? '';
            $query  = DB::table('u_investors as ui')
                    ->join('u_users as uu', 'ui.sales_id', '=', 'uu.user_id')
                    ->where([['ui.sales_id', $userId], ['ui.is_active', 'Yes'], ['uu.is_active', 'Yes']])                    
                    ->whereIn('ui.cif', function($qry) use ($search) {
                        $qry->select('icp.cif')
                            ->from('u_investors_card_priorities as icp')
                            ->where('icp.is_active', 'Yes');
                    })
                    ->select('ui.investor_id', 'ui.fullname', 'ui.cif', 'uu.fullname as sales_name');
            $total  = $query->count();

            if (!empty($query))
            {
                $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
                $query->where(function($qry) use ($search, $like) {
                            $qry->where('ui.fullname', $like, '%'. $search .'%')
                            ->orWhere('ui.cif', $like, '%'. $search .'%');
                        });
                $totFilter = $query->count();
            }

            if (!empty($colName))
                $query->orderBy($colName, $colSort);

            $investors = $query->skip($start)->take($length)->get();

            $cif = $investors->pluck('cif');

            $card = DB::table('u_investors_card_priorities as uicp')
                    ->whereIn('uicp.cif', $cif)
                    ->where('uicp.is_active', 'Yes')
                    ->get();

            $data = $investors->map(function ($investor) use ($card)
            {
                $cardPriority = $card->where('cif', $investor->cif)->first();
                $dropDate = !empty($cardPriority->created_at) || !empty($cardPriority->updated_at) ? !empty($cardPriority->created_at) ? $cardPriority->created_at : $cardPriority->updated_at : null;
                $investor->drop_date = !empty($dropDate) ? date('d/m/Y', strtotime($dropDate)) : null;
                $investor->customer_type = $cardPriority && $cardPriority->is_priority && $cardPriority->pre_approve ? 'Priority' : 'Non Priority';
                $investor->user_status = 'Non Active';
                return $investor;
            });

            return (object) [
                'item' => $data,
                'total' => $total,
                'totalFiltered' => $totFilter ?? $total
            ];
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }   
    }

    private function listInvestorForIncomeExpense($search, $start, $length, $colName, $colSort)
    {
        try
        {
            $auth   = Auth::guard('admin')->user();
            $userId = $auth->id ?? '';
            $query  = DB::table('u_investors as ui')
                    ->where([['ui.is_active', 'Yes'], ['ui.sales_id', $userId]])
                    ->select('ui.investor_id', 'ui.fullname', 'ui.photo_profile', 'ui.cif');
            $total  = $query->count();

            if (!empty($search))
            {
                $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
                $query->where(function($qry) use ($search, $like) {
                    $qry->where('ui.fullname', $like, '%'. $search .'%')
                        ->orWhere('ui.cif', $like, '%'. $search .'%');
                });
                $totFilter = $query->count();
            }
            
            $investors = $query->skip($start)->take($length)->get();
            
            $investorIds = $investors->pluck('investor_id');
            
            $income = DB::table('t_income_expense as tie')
                    ->join('m_financials as mf', 'tie.financial_id', '=', 'mf.financial_id')
                    ->whereIn('tie.investor_id', $investorIds)
                    ->where('tie.is_active', 'Yes')
                    ->where('mf.is_active', 'Yes')
                    ->where('mf.financial_type', 'Income')
                    ->select('tie.investor_id', DB::raw("SUM(CASE WHEN tie.period_of_time = 'Yearly' THEN tie.amount ELSE tie.amount * 12 END) as total_income"))
                    ->groupBy('tie.investor_id')
                    ->get();

            $expense = DB::table('t_income_expense as tie')
                    ->join('m_financials as mf', 'tie.financial_id', '=', 'mf.financial_id')
                    ->whereIn('tie.investor_id', $investorIds)
                    ->where('tie.is_active', 'Yes')
                    ->where('mf.is_active', 'Yes')
                    ->where('mf.financial_type', 'Expense')
                    ->select('tie.investor_id', DB::raw("SUM(CASE WHEN tie.period_of_time = 'Yearly' THEN tie.amount ELSE tie.amount * 12 END) as total_expense"))
                    ->groupBy('tie.investor_id')
                    ->get();

            $data = $investors->map(function ($investor) use ($income, $expense) {
                $incomeAmount = $income->where('investor_id', $investor->investor_id)->first()->total_income ?? null;
                $expenseAmount = $expense->where('investor_id', $investor->investor_id)->first()->total_expense ?? null;
                
                if ($incomeAmount == null && $expenseAmount == null)
                {
                    $cashflow = $status = null;
                }
                else
                {
                    $incomeAmount = $incomeAmount ?? 0;
                    $expenseAmount = $expenseAmount ?? 0;
                    $cashflow = $incomeAmount - $expenseAmount;
                    $status = $cashflow >= 0 ? 'Good' : 'Bad';
                }
    
                return [
                    'investor_id' => $investor->investor_id,
                    'cif' => $investor->cif,
                    'fullname' => $investor->fullname,
                    'photo_profile' => $investor->photo_profile,
                    'cashflow' => $cashflow,
                    'status' => $status
                ];
            });
            
            return (object) [
                'item' => $data,
                'total' => $total,
                'totalFiltered' => $totFilter ?? $total
            ];
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }   
    }

    private function listInvestorForPortfolioCurrent($search, $start, $length, $colName, $colSort)
    {
        try
        {
            $auth       = Auth::guard('admin')->user();
            $userId     = $auth->id ?? '';
            $query      = DB::table('u_investors as ui')
                        ->leftJoin('m_risk_profiles as rp', function($qry) { $qry->on('rp.profile_id', 'ui.profile_id')->where('rp.is_active', 'Yes'); })
                        ->where([['ui.is_active', 'Yes'], ['ui.sales_id', $userId]])->select('ui.investor_id', 'ui.fullname', 
                        'ui.sid', 'ui.photo_profile', 'ui.cif', 'rp.profile_name', 'ui.profile_expired_date');
            $total      = $query->count();

            if (!empty($search))
            {
                $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
                $query->where(function($qry) use ($search, $like) {
                    $qry->where('ui.fullname', $like, '%'. $search .'%')
                        ->orWhere('ui.sid', $like, '%'. $search .'%')
                        ->orWhere('ui.cif', $like, '%'. $search .'%')
                        ->orWhere('rp.profile_name', $like, '%'. $search .'%');
                    
                    if (strtotime($search)) {
                        $searchDate = date('Y-m-d', strtotime(str_replace('/', '-', $search)));
                        $qry->orWhereDate('ui.profile_expired_date', $searchDate);
                    }
                });
                $totFilter = $query->count();
            }

            $investor = $query->skip($start)->take($length)->get();

            $investorIds = $investor->pluck('investor_id');

            $historyNonGoals =  DB::table('t_trans_histories_days as thd')
                ->whereIn('thd.investor_id', $investorIds)
                ->whereDate('thd.history_date', DB::raw('CURRENT_DATE'))
                ->where('thd.is_active', 'Yes')
                ->where(function($qry) {
                    $qry->whereRaw("LEFT(thd.portfolio_id, 1) NOT IN ('2', '3')")
                        ->orWhereNull('thd.portfolio_id');
                })
                ->select('thd.investor_id', DB::raw("SUM(thd.current_balance) AS balance"))
                ->groupBy('thd.investor_id')
                ->get();

            $data = $investor->map(function ($investor) use ($historyNonGoals) {
                $balanceNonGoals = $historyNonGoals->where('investor_id', $investor->investor_id)->first()->balance ?? null;
                $investor->balance_non_goals = $balanceNonGoals;
                return $investor;
            });
            
            return (object) [
                'item' => $data,
                'total' => $total,
                'totalFiltered' => $totFilter ?? $total
            ];
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    private function listInvestorForPortfolioGoals($search, $start, $length, $colName, $colSort)
    {
        try
        {
            $auth       = Auth::guard('admin')->user();
            $userId     = $auth->id ?? '';
            $query      = DB::table('u_investors as ui')
                        ->leftJoin('m_risk_profiles as rp', function($qry) { $qry->on('rp.profile_id', 'ui.profile_id')->where('rp.is_active', 'Yes'); })
                        ->where([['ui.is_active', 'Yes'], ['ui.sales_id', $userId]])
                        ->select('ui.investor_id', 'ui.fullname', 'ui.sid', 'ui.photo_profile', 'ui.cif', 'rp.profile_name',
                                 'ui.profile_expired_date');
            $total      = $query->count();

            if (!empty($search))
            {
                $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
                $query->where(function($qry) use ($search, $like) {
                    $qry->where('ui.fullname', $like, '%'. $search .'%')
                        ->orWhere('ui.sid', $like, '%'. $search .'%')
                        ->orWhere('ui.cif', $like, '%'. $search .'%')
                        ->orWhere('rp.profile_name', $like, '%'. $search .'%');
                    
                    if (strtotime($search)) {
                        $searchDate = date('Y-m-d', strtotime(str_replace('/', '-', $search)));
                        $qry->orWhereDate('ui.profile_expired_date', $searchDate);
                    }
                }); 
                $totFilter = $query->count();               
            }

            $investor = $query->skip($start)->take($length)->get();

            $investorIds = $investor->pluck('investor_id');

            $historyGoals =  DB::table('t_trans_histories_days as thd')
                ->whereIn('thd.investor_id', $investorIds)
                ->whereDate('thd.history_date', DB::raw('CURRENT_DATE'))
                ->where([['thd.is_active', 'Yes'], [DB::raw("LEFT(thd.portfolio_id, 1)"), '2']])
                ->select('thd.investor_id', DB::raw("SUM(thd.current_balance) AS balance"))
                ->groupBy('thd.investor_id')
                ->get();

            $data = $investor->map(function ($investor) use ($historyGoals) {
                $balanceGoals = $historyGoals->where('investor_id', $investor->investor_id)->first()->balance ?? null;
                $investor->balance_goals = $balanceGoals;
                return $investor;
            });                    

            return (object) [
                'item' => $data,
                'total' => $total,
                'totalFiltered' => $totFilter ?? $total
            ];
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    private function listInvestorForReport($search)
    {
        try
        {
            $auth       = Auth::guard('admin')->user();
            $userId     = $auth->id ?? '';
            $investor   = DB::table('u_investors as ui')
                        ->leftJoin('m_risk_profiles as rp', function($qry) { $qry->on('rp.profile_id', 'ui.profile_id')->where('rp.is_active', 'Yes'); })
                        ->where([['ui.is_active', 'Yes'], ['ui.sales_id', $userId]])
                        ->select('ui.investor_id', 'ui.fullname', 'ui.sid', 'ui.photo_profile', 'ui.cif', 'rp.profile_name',
                                 'ui.profile_expired_date');
            $total      = $investor->count();

            if (!empty($search))
            {
                $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
                $investor->where(function($qry) use ($search, $like) {
                    $qry->where('ui.fullname', $like, '%'. $search .'%')
                        ->orWhere('ui.sid', $like, '%'. $search .'%')
                        ->orWhere('ui.cif', $like, '%'. $search .'%')
                        ->orWhere('rp.profile_name', $like, '%'. $search .'%');
                    
                    if (strtotime($search)) {
                        $searchDate = date('Y-m-d', strtotime(str_replace('/', '-', $search)));
                        $qry->orWhereDate('ui.profile_expired_date', $searchDate);
                    }
                });
                $totFilter = $investor->count();
            }                
            
            return (object) [
                'query' => $investor,
                'total' => $total,
                'totalFiltered' => $totFilter ?? $total
            ];
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    private function listInvestorForSales($search, $start, $length, $colName, $colSort)
    {
        try
        {
            $auth       = Auth::guard('admin')->user();
            $userId     = $auth->id ?? '';
            $query      = DB::table('u_investors as ui')
                        ->leftJoin('m_risk_profiles as rp', function($qry) { $qry->on('rp.profile_id', 'ui.profile_id')->where('rp.is_active', 'Yes'); })
                        ->where([['ui.is_active', 'Yes'], ['ui.sales_id', $userId]])
                        ->select('ui.investor_id', 'ui.fullname', 'ui.sid', 'ui.photo_profile', 'ui.cif', 'rp.profile_name',
                                 'ui.profile_expired_date');
            $total      = $query->count();

            if (!empty($search))
            {
                $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
                $query->where(function($qry) use ($search, $like) {
                    $qry->where('ui.fullname', $like, '%'. $search .'%')
                        ->orWhere('ui.sid', $like, '%'. $search .'%')
                        ->orWhere('ui.cif', $like, '%'. $search .'%')
                        ->orWhere('rp.profile_name', $like, '%'. $search .'%');
                    
                    if (strtotime($search)) {
                        $searchDate = date('Y-m-d', strtotime(str_replace('/', '-', $search)));
                        $qry->orWhereDate('ui.profile_expired_date', $searchDate);
                    }
                });                
                $totFilter = $query->count();
            }
            
            $investors = $query->skip($start)->take($length)->get();

            $investorIds = $investors->pluck('investor_id');

            $historyGoals =  DB::table('t_trans_histories_days as thd')
                ->whereIn('thd.investor_id', $investorIds)
                ->whereDate('thd.history_date', DB::raw('CURRENT_DATE'))
                ->where([['thd.is_active', 'Yes'], [DB::raw("LEFT(thd.portfolio_id, 1)"), '2']])
                ->select('thd.investor_id', DB::raw("SUM(thd.current_balance) AS balance"))
                ->groupBy('thd.investor_id')
                ->get();

            $historyNonGoals =  DB::table('t_trans_histories_days as thd')
                ->whereIn('thd.investor_id', $investorIds)
                ->whereDate('thd.history_date', DB::raw('CURRENT_DATE'))
                ->where('thd.is_active', 'Yes')
                ->where(function($qry) {
                    $qry->whereRaw("LEFT(thd.portfolio_id, 1) NOT IN ('2', '3')")
                        ->orWhereNull('thd.portfolio_id');
                })
                ->select('thd.investor_id', DB::raw("SUM(thd.current_balance) AS balance"))
                ->groupBy('thd.investor_id')
                ->get();
                
            $data = $investors->map(function ($investor) use ($historyGoals, $historyNonGoals) {
                $balanceGoals = $historyGoals->where('investor_id', $investor->investor_id)->first()->balance ?? null;
                $balanceNonGoals = $historyNonGoals->where('investor_id', $investor->investor_id)->first()->balance ?? null;
                $investor->balance_goals = $balanceGoals;
                $investor->balance_non_goals = $balanceNonGoals;
                return $investor;
            });
                    
            return (object) [
                'item' => $data,
                'total' => $total,
                'totalFiltered' => $totFilter ?? $total
            ];            
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    public function totalInvestor()
    {
        try
        {
            $auth       = Auth::guard('admin')->user();
            $userId     = $auth->id ?? '';
            $query      = DB::table('u_investors as ui')
                        ->where([['ui.is_active', 'Yes'], ['ui.sales_id', $userId]]);
            //$lastMonth  = $query->whereBetween('ui.created_at', [date('Y-m-01', strtotime('-1 month')), date('Y-m-t', strtotime('-1 month'))]);
            return [
                //'newMember' => $lastMonth->orderBy('ui.created_at', 'desc')->limit(5)->get(),
                //'totalLastMonth' => $lastMonth->count(),
                'total' => $query->count(),
            ];
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }
}