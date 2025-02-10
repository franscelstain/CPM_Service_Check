<?php

namespace App\Http\Controllers\Investor\Financial\Condition;

use App\Models\Investor\Financial\Condition\IncomeExpense;
use App\Http\Controllers\AppController;
use Illuminate\Http\Request;
use Auth;

class IncomeExpenseController extends AppController
{
    public $table = 'Investor\Financial\Condition\IncomeExpense';

    public function index(Request $request)
    {
        try
        {
            $type   = is_array($request->type) ? $request->type : [$request->type];
            $data   = [];
            $qry    = IncomeExpense::select('t_income_expense.*', 'b.financial_name')
                    ->join('m_financials as b', 't_income_expense.financial_id', '=', 'b.financial_id')
                    ->where([['t_income_expense.investor_id', Auth::id()], ['t_income_expense.is_active', 'Yes'], ['b.is_active', 'Yes']])
                    ->whereIn('b.financial_type', $type)
                    ->orderBy('b.sequence_to', 'asc')->get();
            foreach ($qry as $q)
            {
                $data[$q->financial_name][] = [
                    'transaction_id'    => $q->transaction_id,
                    'transaction_name'  => $q->transaction_name,
                    'period_of_time'    => $q->period_of_time,
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
                    $res    = 0;
                    $data   = IncomeExpense::join('m_financials as b', 't_income_expense.financial_id', '=', 'b.financial_id')
                            ->where([['investor_id', Auth::id()], ['b.financial_type', ucwords($t)], ['t_income_expense.is_active', 'Yes'], ['b.is_active', 'Yes']])->get();
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