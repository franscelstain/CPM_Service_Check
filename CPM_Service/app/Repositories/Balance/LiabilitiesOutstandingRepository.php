<?php

namespace App\Repositories\Balance;

use App\Interfaces\Balance\LiabilitiesOutstandingRepositoryInterface;
use App\Models\Crm\ImportedLoan;
use App\Models\Financial\LiabilityOutstanding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class LiabilitiesOutstandingRepository implements LiabilitiesOutstandingRepositoryInterface
{
    public function getIntegration()
    {
        try {
            // $db_cpm     = env('DB_DATABASE');
            // $db_crm     = env('DB2_DATABASE');
            $now = date('Y-m-d');
            $today = date('Y-m-d H:i:s');
            $update = LiabilityOutstanding::where([
                ['outstanding_date', $now],
                ['created_by', 'system.loan'],
                ['is_active', 'Yes']
            ])->delete();
            $importUsr = ImportedLoan::join('u_investors as b', function ($query) {
                $query->on('b.cif', 'imported_loan.cif')->where('b.is_active', 'Yes');
            })
                ->where('imported_loan.deleted', false)
                ->select(
                    DB::raw("CASE WHEN as_of_date IS NOT NULL THEN CONCAT(RIGHT(as_of_date, 4), '-', SUBSTR(as_of_date, 4, 2), '-', LEFT(as_of_date, 2)) ELSE NULL END AS data_date"),
                    'b.investor_id as investor_id',
                    'account_number as account_id',
                    'account_number as liabilities_id',
                    'currency_code as currency',
                    'total_outstanding as outstanding_balance',
                    'product_name as liabilities_name',
                    'plafon',
                    DB::raw("'$today' as outstanding_date"),
                    DB::raw("'system.loan' as created_by"),
                    DB::raw("'::1' as created_host"),
                    DB::raw("'$today' as created_at"),
                )->distinct();
            $insert = LiabilityOutstanding::insertUsing([
                'data_date',
                'investor_id',
                'account_id',
                'liabilities_id',
                'currency',
                'outstanding_balance',
                'liabilities_name',
                'plafon',
                'outstanding_date',
                'created_by',
                'created_host',
                'created_at'
            ], $importUsr);

            return [
                'insert' => $insert,
                'update' => $update
            ];
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function getLiabilities(Request $request, $id)
    {
        try {
            $month      = !empty($request->month) ? $request->month : 'm';
            $year       = !empty($request->year) ? $request->year : 'Y';
            $start_date = date($year . '-' . $month . '-01');
            $end_date   = date('Y-m-t', strtotime($start_date));  
            $latest     = DB::table('t_liabilities_outstanding')
                        ->where([['is_active', 'Yes'],
                            ['investor_id', $id],
                            ['outstanding_date', '>=', $start_date],
                            ['outstanding_date', '<=', $end_date]
                        ])
                        ->max('outstanding_date');

            $pembiayaan     = DB::table('t_liabilities_outstanding as tlo')
                            ->leftJoin('t_stg_exchange_rates as er', function ($q) {
                                $q->on('tlo.currency', 'er.currency')
                                ->on('tlo.data_date', 'er.date');
                            })
                            ->where(function($whr) {
                                $whr->where(DB::raw("LOWER(tlo.liabilities_type)"), '!=', 'card')
                                    ->orWhereNull('tlo.liabilities_type');
                            })
                            ->where([
                                ['tlo.investor_id', $id],
                                ['tlo.is_active', 'Yes'],
                                ['tlo.outstanding_balance', '>=', 1],
                                ['tlo.outstanding_date', $latest]
                            ])
                            ->select('tlo.data_date', 'tlo.liabilities_name', 'tlo.liabilities_id', 'er.rate', 'tlo.currency',
                                     'tlo.outstanding_balance', 'tlo.outstanding_balance_idr', 'tlo.collectability_cif_name')
                            ->orderBy('tlo.data_date', 'desc')
                            ->get();
            $hasanah_card   = DB::table('t_liabilities_outstanding as tlo')
                            ->where([
                                ['tlo.investor_id', $id],
                                ['tlo.is_active', 'Yes'],
                                ['tlo.outstanding_balance', '>=', 1],
                                ['tlo.outstanding_date', $latest],
                                [DB::raw("LOWER(tlo.liabilities_type)"), 'card']
                            ])
                            ->select('tlo.data_date', 'tlo.liabilities_sub_type', 'tlo.collectability_account_name',
                                    'tlo.account_id', 'tlo.outstanding_balance', 'tlo.remaining_amount', 'tlo.limit_amount')
                            ->get();
            return ['pembiayaan' => $pembiayaan ?? [], 'hasanah_card' => $hasanah_card ?? []];
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }
}