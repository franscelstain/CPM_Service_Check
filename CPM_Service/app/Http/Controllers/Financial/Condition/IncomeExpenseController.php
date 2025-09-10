<?php

namespace App\Http\Controllers\Financial\Condition;

use App\Http\Controllers\AppController;
use App\Models\Financial\Condition\IncomeExpense;
use App\Models\Users\Investor\Investor;
use App\Traits\Financial\Condition\FinancialRatio;
use Illuminate\Http\Request;
use Auth;

class IncomeExpenseController extends AppController
{
    use FinancialRatio;
    
    public $table = 'Financial\Condition\IncomeExpense';

    public function index(Request $request)
    {
        return $this->findata($request, Auth::id());
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    private function findata($request, $inv_id)
    {
        try
        {
            $type   = is_array($request->type) ? $request->type : [$request->type];
            $data   = [];
            $qry    = IncomeExpense::select('t_income_expense.*', 'b.financial_name')
                    ->join('m_financials as b', 't_income_expense.financial_id', '=', 'b.financial_id')
                    ->where([['t_income_expense.investor_id', $inv_id], ['t_income_expense.is_active', 'Yes'], ['b.is_active', 'Yes']])
                    ->whereIn('b.financial_type', $type)
                    ->orderBy('b.sequence_to', 'asc')->get();
            foreach ($qry as $q)
            {
                $data[$q->financial_name][] = [
                    'transaction_id'    => $q->transaction_id,
                    'transaction_name'  => $q->transaction_name,
                    'period_of_time'    => $q->period_of_time,
                    'amount'            => $q->amount, 
                    'updated_at'        => $q->updated_at,
                    't'                 => 0
                ];
            }
            return $this->app_response('Financial', ['list' => $data]);
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
            $ie     = [];
            $limit  = !empty($request->limit) ? $request->limit : 10;
            $page   = !empty($request->page) ? $request->page : 1;
            $offset = ($page-1)*$limit;
            $data   = Investor::where([['sales_id', $this->auth_user()->id], ['is_active', 'Yes']]);

            if (!empty($request->search))
            {
               $data  = $data->where(function($qry) use ($request) {
                            $qry->where('u_investors.fullname', 'ilike', '%'. $request->search .'%')
                                ->orWhere('u_investors.cif', 'ilike', '%'. $request->search .'%');
                        });
            }
            foreach ($data->get() as $dt)
            {
                $ie[] = [
                    'investor_id'   => $dt->investor_id,
                    'cif'           => $dt->cif,
                    'fullname'      => $dt->fullname,
                    'photo_profile' => $dt->photo_profile,
                    'cashflow'      => $this->total_amount($dt->investor_id, 'Income') - $this->total_amount($dt->investor_id, 'Expense')
                ];
            }
            $total = $data->count();
            $total_data = $page*$limit;
            $paginate = [
                'current_page'  => $page,
                'data'          => $ie,
                'from'          => $page > 1 ?  1 + (($page-1) * $limit) : 1,
                'per_page'      => $limit,
                'to'            => $total_data >= $total ? $total : $total_data,
                'total'         => $total
            ];

            return $this->app_response('Income Expense',  $paginate);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
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
        return $this->total_income_expense($request, Auth::id());
    }

    public function totalByName(Request $request)
    {
        return $this->total_with_name(Auth::id(), $request->input('name'), $request->input('type'));
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
    
    public function total_for_sales(Request $request, $id)
    {
        return $this->total_income_expense($request, $id);
    }
    
    public function total_for_sales_with_name(Request $request, $id)
    {
        return $this->total_with_name($id, $request->input('name'), $request->input('type'));
    }
    
    private function total_income_expense($request, $inv_id)
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
                    $res    = 0;
                    $data   = IncomeExpense::join('m_financials as b', 't_income_expense.financial_id', '=', 'b.financial_id')
                            ->where([['investor_id', $inv_id], ['b.financial_type', ucwords($t)], ['t_income_expense.is_active', 'Yes'], ['b.is_active', 'Yes']])->get();
                    foreach ($data as $dt)
                    {
                        $res += $dt->period_of_time == 'Yearly' ? $dt->amount : $dt->amount * 12;
                    }                    
                    $total = $total == 0 ? $res : $total - $res;
                }
                $sum[$t] = $t == end($typ) ? $total : $res;
            }
            return $this->app_response('Total Income & Expense', $sum);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}