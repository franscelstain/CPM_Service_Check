<?php

namespace App\Repositories\Users;

use App\Interfaces\Users\InvestorRepositoryInterface;
use App\Models\Auth\Investor as AuthInvestor;
use App\Models\Users\Investor\Investor;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Auth;
use DB;
use Session;


class InvestorRepository implements InvestorRepositoryInterface
{
    public function baseInvestorSales($sales_id)
    {
        return DB::table('u_investors as ui')
                ->where('ui.sales_id', $sales_id)
                ->where('ui.is_active', 'Yes');
    }

    public function clearOtpById(int $id)
    {
        return Investor::where('investor_id', $id)
                ->update([
                    'otp' => null,
                    'otp_created' => null
                ]);
    }

    public function countInvestorsBySales($salesId)
    {
        $cacheKey = "investor_count_sales_{$salesId}";

        return Cache::remember($cacheKey, Carbon::now()->addHour(), function () use ($salesId) {
            return Investor::where('sales_id', $salesId)
                           ->where('is_active', 'Yes')
                           ->count();
        });
    }    

    public function countInvestorPriority()
    {
        return DB::table('u_investors as ui')
                ->join('u_investors_card_priorities as uicp', 'ui.cif', '=', 'uicp.cif')
                ->where([['ui.is_active', 'Yes'], ['uicp.is_active', 'Yes']])
                ->distinct('ui.investor_id') 
                ->count();
    }

    public function deactivateInvestorById($invId)
    {
        return DB::table('u_investors')
                ->where('investor_id', $invId)
                ->where('is_active', 'Yes')
                ->update(['is_enable' => 'No']);
    }

    public function deactivateInvestorByEmail(string $email)
    {
        return DB::table('u_investors')
                ->where('email', $email)
                ->where('is_active', 'Yes')
                ->update(['is_enable' => 'No']);
    }

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

    public function detailInvestorBySales($inv_id, $sales_id)
    {
        return DB::table('investors as ui')
                ->where('ui.investor_id', $inv_id)
                ->where('ui.sales_id', $sales_id)
                ->where('ui.is_active', 'Yes')
                ->first();
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

    public function findByEmail(string $email) {
        return DB::table('u_investors as ui')
                ->where('ui.email', $email)
                ->where('ui.is_active', 'Yes')
                ->first();
    }

    public function findByIdWithOtp(string $investorId, string $otp) {
        return DB::table('u_investors as ui')
                ->where('ui.investor_id', $investorId)
                ->where('ui.otp', $otp)
                ->where('ui.is_active', 'Yes')
                ->first();
    }

    public function getInvestorsBySalesWithPagination($salesId, $search = '', $start = 0, $length = 10, $colName = 'fullname', $colSort = 'asc')
    {
        $query = Investor::where('u_investors.sales_id', $salesId)->where('u_investors.is_active', 'Yes');

        // Kondisi tambahan jika ada pencarian
        if (!empty($search)) {
            $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($qry) use ($search, $like) {
                $qry->where('u_investors.fullname', $like, '%' . $search . '%')
                    ->orWhere('u_investors.cif', $like, '%' . $search . '%');
            });
        }

        return $query->orderBy($colName, $colSort)
                    ->skip($start)
                    ->take($length)
                    ->get();
    }

    public function investorForSales($salesId, $search, $limit, $page, $colName, $colSort)
    {
        $query = DB::table('u_investors as ui')
                ->where([['ui.is_active', 'Yes'], ['ui.sales_id', $salesId]]);

        if (!empty($search)) {
            $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
            $query->where(function($qry) use ($search, $like) {
                $qry->where('ui.fullname', $like, '%'. $search .'%')
                    ->orWhere('ui.cif', $like, '%'. $search .'%');
            });
        }
            
        if (!empty($colName) && !empty($colSort)) {
            $query->orderBy($colName, $colSort);
        }

        return $query->select('ui.cif', 'ui.fullname')
                    ->paginate($limit, ['*'], 'page', $page);
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
            elseif ($request->dest == 'income-expense')
                $sql = $this->listInvestorForIncomeExpense($search, $offset, $limit, $colName, $colSort);
            elseif ($request->dest == 'portfolio-current')
                $sql = $this->listInvestorForPortfolioCurrent($search, $offset, $limit, $colName, $colSort);
            elseif ($request->dest == 'report')
                $sql = $this->listInvestorForReport($search);
            elseif ($request->dest == 'sales')
                $sql = $this->listInvestorForSales($search, $offset, $limit, $colName, $colSort);

            if (isset($sql) && empty($sql->errors))
            {
                if (isset($sql->item)) {
                    $items = $sql->item;
                }
                else {
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
            $auth       = Auth::guard('admin')->user();
            $userId     = $auth->id ?? '';
            $page       = $length > 0 ? $start / $length + 1 : 1;
            $subquery   = DB::table('u_investors as ui')
                        ->where([['ui.is_active', 'Yes'], ['ui.sales_id', $userId]])
                        ->select('ui.investor_id', 'ui.fullname', 'ui.photo_profile', 'ui.cif');
            $total      = $subquery->count();            
            $like       = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
            $investors  = $subquery->when(!empty($search), function ($query) use ($search, $like) {
                            $query->where(function ($q) use ($search, $like) {
                                $q->where('ui.cif', $like, "%$search%")
                                ->orWhere('ui.fullname', $like, "%$search%");
                            });
                        })                
                        ->when(!empty($colName) && !empty($colSort), function ($q) use ($colName, $colSort) {
                            $q->orderBy($colName, $colSort);
                        })                    
                        ->paginate($length, ['*'], 'page', $page);
            $totFilter  = !empty($search) ? $investors->count() : $total;
            
            if ($investors->isEmpty())
            {
                return (object) [
                    'item' => [],
                    'total' => $total,
                    'totalFiltered' => $totFilter ?? $total
                ];
            }

            $investorIds = $investors->pluck('investor_id')->toArray();
            
            $assets = DB::table('t_assets_liabilities as tal')
                    ->join('m_financials as mf', 'tal.financial_id', '=', 'mf.financial_id')
                    ->whereIn('tal.investor_id', $investorIds)
                    ->where('tal.is_active', 'Yes')
                    ->where('mf.is_active', 'Yes')
                    ->where('mf.financial_type', 'Assets')
                    ->select('tal.investor_id', DB::raw('SUM(tal.amount) as total_assets'))
                    ->groupBy('tal.investor_id')
                    ->get()
                    ->keyBy('investor_id');

            $subOut = DB::table('t_assets_outstanding as tao')
                    ->selectRaw('DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                        tao.investor_id,
                        tao.balance_amount
                    ')
                    ->join('u_investors as ui', 'tao.investor_id', '=', 'ui.investor_id')
                    ->join('m_products as mp', 'mp.product_id', '=', 'tao.product_id')
                    ->join('m_asset_class as mac', 'mac.asset_class_id', '=', 'mp.asset_class_id')
                    ->join('m_asset_categories as mact', 'mact.asset_category_id', '=', 'mac.asset_category_id')
                    ->whereIn('tao.investor_id', $investorIds)
                    ->where('tao.outstanding_date', DB::raw('CURRENT_DATE'))
                    ->where('tao.is_active', 'Yes')
                    ->where('ui.is_active', 'Yes')
                    ->where('mp.is_active', 'Yes')
                    ->where('mac.is_active', 'Yes')
                    ->where('mact.is_active', 'Yes')
                    ->orderBy('tao.investor_id')
                    ->orderBy('tao.account_no')
                    ->orderBy('tao.product_id')
                    ->orderByDesc('tao.data_date')
                    ->orderByDesc('tao.outstanding_id');            
            
            $outstandingAmounts = DB::table(DB::raw("({$subOut->toSql()}) as a"))
                ->mergeBindings($subOut)
                ->select(
                    'investor_id',
                    DB::raw('SUM(balance_amount) as total_amount')
                )
                ->groupBy('investor_id')
                ->get()
                ->keyBy('investor_id');
            
            $liabilities = DB::table('t_assets_liabilities as tal')
                ->join('m_financials as mf', 'tal.financial_id', '=', 'mf.financial_id')
                ->whereIn('tal.investor_id', $investorIds)
                ->where('tal.is_active', 'Yes')
                ->where('mf.is_active', 'Yes')
                ->where('mf.financial_type', 'Liabilities')
                ->select('tal.investor_id', DB::raw('SUM(tal.amount) as total_liabilities'))
                ->groupBy('tal.investor_id')
                ->get()
                ->keyBy('investor_id');            

            $subLiab = DB::table('t_liabilities_outstanding as tlo')
                        ->join('u_investors as ui', 'tlo.investor_id', '=', 'ui.investor_id')
                        ->whereIn('tlo.investor_id', $investorIds)
                        ->where('tlo.outstanding_date', DB::raw('CURRENT_DATE'))
                        ->where('tlo.is_active', 'Yes')
                        ->where('ui.is_active', 'Yes')
                        ->selectRaw('DISTINCT ON (tlo.investor_id, tlo.liabilities_id)
                            tlo.investor_id,
                            tlo.outstanding_balance
                        ')
                        ->orderBy('tlo.investor_id')
                        ->orderBy('tlo.liabilities_id')
                        ->orderByDesc('tlo.data_date')
                        ->orderByDesc('tlo.liabilities_outstanding_id');

            $liabilitiesAmounts = DB::table(DB::raw("({$subLiab->toSql()}) as a"))
                ->mergeBindings($subLiab)
                ->select(
                    'investor_id',
                    DB::raw('SUM(outstanding_balance) as total_amount')
                )
                ->groupBy('investor_id')
                ->get()
                ->keyBy('investor_id');

            $data = $investors->map(function ($investor) use ($assets, $outstandingAmounts, $liabilities, $liabilitiesAmounts) {
                $assetAmount        = $assets->get($investor->investor_id)->total_assets ?? null;
                $latestAssetAmount  = $outstandingAmounts->get($investor->investor_id)->total_amount ?? null;
                
                if ($assetAmount == null && $latestAssetAmount == null) {
                    $totalAssets = null;
                } else {
                    $assetAmount = $assetAmount ?? 0;
                    $latestAssetAmount = $latestAssetAmount ?? 0;
                    $totalAssets = $assetAmount + $latestAssetAmount;
                }
    
                $liabilityAmount        = $liabilities->get($investor->investor_id)->total_liabilities ?? 0;
                $latestLiabilityAmount  = $liabilitiesAmounts->get($investor->investor_id)->total_amount ?? 0;
                
                if ($liabilityAmount == null && $latestLiabilityAmount == null) {
                    $totalLiabilities = null;
                } else {
                    $liabilityAmount = $liabilityAmount ?? 0;
                    $latestLiabilityAmount = $latestLiabilityAmount ?? 0;
                    $totalLiabilities = $liabilityAmount + $latestLiabilityAmount;
                }

                if ($totalAssets == null && $totalLiabilities == null) {
                    $networth = $status = null;
                } else {
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
                'totalFiltered' => $totFilter ?? $total,
                'start' => $start
            ];
        } catch (\Exception $e) {
            \Log::error('Error in listInvestorForAssetsLiabilities: ' . $e->getMessage(), [
                'exception' => $e,
                'stack_trace' => $e->getTraceAsString(),
            ]);
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }   
    }

    private function listInvestorForIncomeExpense($search, $start, $length, $colName, $colSort)
    {
        try
        {
            $auth   = Auth::guard('admin')->user();
            $userId = $auth->id ?? '';

            $sortable = ['fullname', 'cif'];
            $colName  = in_array($colName, $sortable, true) ? $colName : 'fullname';
            $colSort  = strtolower($colSort) === 'desc' ? 'desc' : 'asc';

            $base  = DB::table('u_investors as ui')
                    ->where([['ui.is_active', 'Yes'], ['ui.sales_id', $userId]])
                    ->select('ui.investor_id', 'ui.fullname', 'ui.photo_profile', 'ui.cif');
            // $total  = $query->count();
            $total = (clone $base)->count();

            $agg = DB::table('t_income_expense as tie')
                ->join('m_financials as mf', 'tie.financial_id', '=', 'mf.financial_id')
                ->where('tie.is_active', 'Yes')
                ->where('mf.is_active', 'Yes')
                ->select([
                    'tie.investor_id',
                    DB::raw("SUM(CASE WHEN mf.financial_type = 'Income'
                                    THEN CASE WHEN tie.period_of_time = 'Yearly' THEN tie.amount ELSE tie.amount * 12 END
                                    ELSE 0 END) AS total_income"),
                    DB::raw("SUM(CASE WHEN mf.financial_type = 'Expense'
                                    THEN CASE WHEN tie.period_of_time = 'Yearly' THEN tie.amount ELSE tie.amount * 12 END
                                    ELSE 0 END) AS total_expense"),
                ])
                ->groupBy('tie.investor_id');

            $filtered = (clone $base)
                ->leftJoinSub($agg, 'fx', 'fx.investor_id', '=', 'ui.investor_id')
                ->when(!empty($search), function ($q) use ($search) {
                    $like = env('DB_CONNECTION') === 'pgsql' ? 'ilike' : 'like';
                    $q->where(function ($qq) use ($search, $like) {
                        $qq->where('ui.fullname', $like, "%{$search}%")
                        ->orWhere('ui.cif', $like, "%{$search}%");
                    });
                });

            $totFilter = (clone $filtered)->count();

            $rows = $filtered
                ->select([
                    'ui.investor_id',
                    'ui.cif',
                    'ui.fullname',
                    'ui.photo_profile',
                    DB::raw("CASE 
                                WHEN fx.total_income IS NULL AND fx.total_expense IS NULL THEN NULL
                                WHEN (fx.total_income - fx.total_expense) >= 0 THEN 'Good'
                                ELSE 'Bad'
                            END AS status"),
                    DB::raw("(fx.total_income - fx.total_expense) AS cashflow"),
                ])
                ->orderBy($colName, $colSort)
                ->offset($start)
                ->limit($length)
                ->get();
            
            return (object) [
                'item' => $rows,
                'total' => $total,
                'totalFiltered' => $totFilter
            ];
        }
        catch (\Exception $e) {
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
            $auth = Auth::guard('admin')->user();
            $userId = $auth->id ?? '';
            $sortable = [ 'fullname','cif','sid','profile_name','profile_expired_date' ]; 
            $colName = in_array($colName, $sortable, true) ? $colName : 'fullname'; 
            $colSort = strtolower($colSort) === 'desc' ? 'desc' : 'asc';

            $dateToday = date('Y-m-d'); 
            
            $qGoals = DB::table('t_trans_histories_days as thd') 
                    ->select(
                        'thd.investor_id', 
                        DB::raw('SUM(thd.current_balance) AS balance_goals')
                    ) 
                    ->where('thd.is_active', 'Yes') 
                    ->where('thd.portfolio_type', 'Goal') 
                    ->whereRaw("CAST(thd.history_date AS DATE) = ?", [$dateToday]) 
                    ->groupBy('thd.investor_id'); 
            
            $latestRN = DB::query()
                    ->fromSub(
                        DB::table('t_assets_outstanding as tao') 
                        ->where('tao.is_active', 'Yes') 
                        ->whereRaw("CAST(tao.outstanding_date AS DATE) = ?", [$dateToday]) 
                        ->selectRaw(" 
                            tao.investor_id, 
                            tao.account_no, 
                            tao.product_id, 
                            tao.balance_amount, 
                            ROW_NUMBER() OVER ( 
                                PARTITION BY tao.investor_id, tao.account_no, tao.product_id 
                                ORDER BY tao.data_date DESC, tao.outstanding_id DESC 
                            ) AS rn 
                        "), 
                        't'
                    )
                    ->where('t.rn', 1);

            $qNonGoals = DB::query()
                        ->fromSub($latestRN, 'x')
                        ->select(
                            'x.investor_id', 
                            DB::raw('SUM(x.balance_amount) AS balance_non_goals')
                        )
                        ->groupBy('x.investor_id');
            
            $base = DB::table('u_investors as ui')
                    ->leftJoin('m_risk_profiles as rp', function ($join) { 
                        $join->on('rp.profile_id', '=', 'ui.profile_id') 
                            ->where('rp.is_active', 'Yes');
                    })
                    ->leftJoinSub($qGoals, 'g', 'g.investor_id', '=', 'ui.investor_id')
                    ->leftJoinSub($qNonGoals, 'ng', 'ng.investor_id', '=', 'ui.investor_id')
                    ->where('ui.is_active', 'Yes')
                    ->where('ui.sales_id', $userId); 
            
            $total = (clone $base)->count();

            if (!empty($search)) { 
                $s = strtolower(str_replace('/', '-', $search)); 
                $like = env('DB_CONNECTION') == 'pgsql' ? 'ILIKE' : 'LIKE'; 
                $base->where(function ($q) use ($s, $like) { 
                    $q->whereRaw("ui.fullname {$like} ?", ["%{$s}%"])
                        ->orWhereRaw("ui.sid {$like} ?", ["%{$s}%"])
                        ->orWhereRaw("ui.cif {$like} ?", ["%{$s}%"])
                        ->orWhereRaw("rp.profile_name {$like} ?", ["%{$s}%"]); 
                        
                    if (strtotime($s)) { 
                        $d = date('Y-m-d', strtotime($s)); 
                        $q->orWhereRaw("CAST(ui.profile_expired_date AS DATE) = ?", [$d]); 
                    } 
                }); 
            } 
            
            $totalFiltered = (clone $base)->count();
            
            $query = (clone $base)
                    ->select([ 
                        'ui.investor_id', 
                        'ui.fullname', 
                        'ui.sid', 
                        'ui.photo_profile', 
                        'ui.cif', 
                        'rp.profile_name', 
                        'ui.profile_expired_date', 
                        DB::raw('g.balance_goals'), 
                        DB::raw('
                            CASE 
                                WHEN g.balance_goals IS NOT NULL AND 
                                    ng.balance_non_goals IS NOT NULL 
                                    THEN ng.balance_non_goals - g.balance_goals 
                                ELSE ng.balance_non_goals END AS balance_non_goals
                        ')
                    ]); 
            
            $rows = $query->orderBy($colName, $colSort)
                    ->offset($start)
                    ->limit($length)
                    ->get();
                    
            return (object) [
                'item' => $rows,
                'total' => $total,
                'totalFiltered' => $totalFiltered
            ];           
        }
        catch (\Exception $e)
        {
            \Log::error('Error in listInvestorForSales: ' . $e->getMessage(), [
                'exception' => $e,
                'stack_trace' => $e->getTraceAsString(),
            ]);
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    public function listPriorityCard($search, $limit = 10, $page = 1, $colName = 'fullname', $colSort = 'asc')
    {
        $query   = DB::table('u_investors as ui')
                ->join('u_investors_card_priorities as uicp', 'ui.cif', '=', 'uicp.cif')
                ->where([['ui.is_active', 'Yes'], ['uicp.is_active', 'Yes']]);

        if (!empty($search)) {
            $like = env('DB_CONNECTION') === 'pgsql' ? 'ilike' : 'like';
            $query->where(function($qry) use ($search, $like) {
                $qry->where('ui.fullname', $like, '%' . $search . '%');
            });
        }

        $sub = $query->select(
                    'ui.investor_id', 'ui.fullname', 
                    DB::raw("CASE WHEN uicp.is_priority IS TRUE THEN 'Priority' ELSE 'Non Priority' END category"),
                    DB::raw("CASE WHEN uicp.pre_approve IS TRUE THEN 'Yes' ELSE 'No' END pre_approve")
                    )
                ->distinct();

        return DB::table(DB::raw("({$sub->toSql()}) as sub"))
            ->mergeBindings($sub)
            ->orderBy($colName, $colSort)
            ->paginate($limit, ['*'], 'page', $page);
    }

    public function listWithBalanceForSales($salesId, $search, $limit, $page, $colName, $colSort)
    {
        $connection = env('DB_CONNECTION');
        $query = DB::table('u_investors as ui')
                ->leftJoin('m_risk_profiles as rp', function($join) { 
                    $join->on('rp.profile_id', 'ui.profile_id')
                        ->where('rp.is_active', 'Yes');
                })
                ->where([['ui.is_active', 'Yes'], ['ui.sales_id', $salesId]]);
        
        if (!empty($search)) {
            $like = $connection === 'pgsql' ? 'ilike' : 'like';
            $query->where(function($qry) use ($search, $like) {
                $qry->where('ui.fullname', $like, '%'. $search .'%')
                    ->orWhere('ui.sid', $like, '%'. $search .'%')
                    ->orWhere('ui.cif', $like, '%'. $search .'%')
                    ->orWhere('rp.profile_name', $like, '%'. $search .'%')
                    ->orWhere('ui.profile_expired_date', $like, '%'. $search .'%');
            });
        }

        // Subquery to calculate balance_goals
        $balanceGoalsSubquery = DB::table('t_trans_histories_days as thd')
            ->select('thd.investor_id', DB::raw("SUM(thd.current_balance) AS balance_goals"))
            ->whereDate('thd.history_date', DB::raw('CURRENT_DATE'))
            ->where([['thd.is_active', 'Yes'], [DB::raw("LEFT(thd.portfolio_id, 1)"), '2']])
            ->groupBy('thd.investor_id');
        
        // Subquery for balance_non_goals
        $balanceNonGoalsSubquery = DB::table('t_trans_histories_days as thd')
            ->select('thd.investor_id', DB::raw("SUM(thd.current_balance) AS balance_non_goals"))
            ->whereDate('thd.history_date', DB::raw('CURRENT_DATE'))
            ->where('thd.is_active', 'Yes')
            ->where(function ($qry) {
                $qry->whereRaw("LEFT(thd.portfolio_id, 1) NOT IN ('2', '3')")
                    ->orWhereNull('thd.portfolio_id');
            })
            ->groupBy('thd.investor_id');

        // Join the subquery to add balance_goals as a column
        $query->leftJoinSub($balanceGoalsSubquery, 'goals', 'goals.investor_id', '=', 'ui.investor_id')
            ->leftJoinSub($balanceNonGoalsSubquery, 'non_goals', 'non_goals.investor_id', '=', 'ui.investor_id');

        if (!empty($colName)) {
            $defaultSort = $colName === 'profile_expired_date' ? "'1970-01-01'" : "''";
            switch ($colName) {
                case 'profile_expired_date':
                    $defaultSort = "'1970-01-01'";
                    break;
                case 'balance_goals':
                    $defaultSort = 0;
                    break;
                case 'balance_non_goals':
                    $defaultSort = 0;
                    break;
                default:
                    $defaultSort = "''";
                    break;
            }

            if ($connection === 'pgsql') {
                $query->orderByRaw("COALESCE($colName, $defaultSort) $colSort");
            } else {
                $query->orderByRaw("IFNULL($colName, $defaultSort) $colSort");
            }
        }


        return $query->select(
                    'ui.investor_id', 
                    'ui.fullname', 
                    'ui.sid', 
                    'ui.photo_profile', 
                    'ui.cif', 
                    'rp.profile_name',
                    'ui.profile_expired_date',
                    'goals.balance_goals',
                    'non_goals.balance_non_goals'
                )
                ->paginate($limit, ['*'], 'page', $page);

    }

    public function listWithGoalsForSales($query, $search, $limit, $page, $colName, $colSort)
    {
        $query->leftJoin('m_risk_profiles as rp', function($join) { 
                    $join->on('rp.profile_id', 'ui.profile_id')
                            ->where('rp.is_active', 'Yes');
                });

        if (!empty($search)) {
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
        }

        return $query->orderBy($colName, $colSort)
            ->select(
                'ui.investor_id', 
                'ui.fullname', 
                'ui.sid', 
                'ui.photo_profile', 
                'ui.cif', 
                'rp.profile_name',                        
                'ui.profile_expired_date'
            )
            ->paginate($limit, ['*'], 'page', $page);
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

    public function updateLastActivityByEmail(string $email, $token)
    {
        return Investor::where('email', $email)
                ->update([
                    'last_activity' => date('Y-m-d H:i:s'),
                    'token' => $token
                ]);
    }

    public function updateProfileById(int $id, array $data)
    {
        return Investor::where('investor_id', $id)
                ->update($data);
    }

    public function updateOtpById(int $id, string $otp)
    {
        return Investor::where('investor_id', $id)
                ->update([
                    'otp' => $otp,
                    'otp_created' => Carbon::now(),
                    'token' => null,
                ]);
    }
}