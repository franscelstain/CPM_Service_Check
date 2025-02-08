<?php

namespace App\Repositories\Finance;

use App\Interfaces\Finance\LiabilitiesOutstandingRepositoryInterface;
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

    public function liabilityHasanahCard($investorId, $latestDate)
    {
        // Subquery untuk mendapatkan data_date terbaru
        $subQuery = DB::table('t_liabilities_outstanding as tlo')
            ->select(
                'tlo.liabilities_id', 
                DB::raw('MAX(tlo.data_date) as max_data_date'),
                DB::raw('MAX(tlo.created_at) as max_created_at')
            )
            ->where([
                ['tlo.investor_id', $investorId],
                ['tlo.is_active', 'Yes'],
                ['tlo.outstanding_date', $latestDate],
                [DB::raw("LOWER(tlo.liabilities_type)"), '=', 'card'],
            ])
            ->groupBy('tlo.liabilities_id');

        return DB::table('t_liabilities_outstanding as tlo')
            ->joinSub($subQuery, 'latest_data', function ($join) {
                $join->on('tlo.liabilities_id', '=', 'latest_data.liabilities_id')
                    ->on('tlo.data_date', '=', 'latest_data.max_data_date')
                    ->on('tlo.created_at', '=', 'latest_data.max_created_at');
            })
            ->where([
                ['tlo.investor_id', $investorId],
                ['tlo.is_active', 'Yes'],
                ['tlo.outstanding_balance', '>=', 1],
                ['tlo.outstanding_date', $latestDate],
                [DB::raw("LOWER(tlo.liabilities_type)"), '=', 'card'],
            ])
            ->select(
                'tlo.data_date',
                'tlo.liabilities_sub_type',
                'tlo.collectability_account_name',
                'tlo.account_id',
                'tlo.outstanding_balance',
                'tlo.remaining_amount',
                'tlo.limit_amount',
                DB::raw('MAX(tlo.data_date) OVER () as latest_data_date')
            )
            ->distinct()
            ->get();
    }

    public function liabilityLatestDate($investorId, $outDate)
    {
        return DB::table('t_liabilities_outstanding')
            ->where('investor_id', $investorId)
            ->where('outstanding_date', '<=', $outDate)
            ->where('is_active', 'Yes')
            ->max('outstanding_date');
    }
    
    public function liabilityPembiayaan($investorId, $latestDate)
    {
        // Subquery untuk mendapatkan data_date terbaru
        $subQuery = DB::table('t_liabilities_outstanding as tlo')
            ->select(
                'tlo.liabilities_id', 
                DB::raw('MAX(tlo.data_date) as max_data_date'),
                DB::raw('MAX(tlo.created_at) as max_created_at')
            )
            ->where(function ($query) {
                $query->where(DB::raw("LOWER(tlo.liabilities_type)"), '!=', 'card')
                      ->orWhereNull('tlo.liabilities_type');
            })
            ->where([
                ['tlo.investor_id', $investorId],
                ['tlo.is_active', 'Yes'],
                ['tlo.outstanding_date', $latestDate],
            ])
            ->groupBy('tlo.liabilities_id');

        return DB::table('t_liabilities_outstanding as tlo')
            ->joinSub($subQuery, 'latest_data', function ($join) {
                $join->on('tlo.liabilities_id', '=', 'latest_data.liabilities_id')
                    ->on('tlo.data_date', '=', 'latest_data.max_data_date')
                    ->on('tlo.created_at', '=', 'latest_data.max_created_at');
            })
            ->leftJoin('t_stg_exchange_rates as er', function ($join) {
                $join->on('tlo.currency', '=', 'er.currency')
                     ->on('tlo.data_date', '=', 'er.date');
            })
            ->where(function ($query) {
                $query->where(DB::raw("LOWER(tlo.liabilities_type)"), '!=', 'card')
                      ->orWhereNull('tlo.liabilities_type');
            })
            ->where([
                ['tlo.investor_id', $investorId],
                ['tlo.is_active', 'Yes'],
                ['tlo.outstanding_balance', '>=', 1],
                ['tlo.outstanding_date', $latestDate],
            ])
            ->select(
                'tlo.data_date',
                'tlo.liabilities_name',
                'tlo.liabilities_id',
                'er.rate',
                'tlo.currency',
                'tlo.outstanding_balance',
                'tlo.outstanding_balance_idr',
                'tlo.collectability_cif_name',
                DB::raw('MAX(tlo.data_date) OVER () as latest_data_date')
            )
            ->distinct()
            ->get();
    }

    public function totalLiability($investorId, $outDate)
    {
        return DB::table('t_liabilities_outstanding')
            ->where('investor_id', $investorId)
            ->where('outstanding_date', $outDate)
            ->where('is_active', 'Yes')
            ->sum('outstanding_balance');
    }
}