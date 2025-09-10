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
        return DB::select("
            SELECT DISTINCT ON (tlo.investor_id, tlo.liabilities_id)
                tlo.liabilities_sub_type,
                tlo.collectability_account_name,
                tlo.account_id,
                tlo.outstanding_balance,
                tlo.remaining_amount,
                tlo.limit_amount,
                tlo.data_date
            FROM t_liabilities_outstanding as tlo
            JOIN u_investors ui ON tlo.investor_id = ui.investor_id
            WHERE tlo.investor_id = ?
                AND tlo.outstanding_date = ?
                AND LOWER(tlo.liabilities_type) = 'card'
                AND tlo.outstanding_balance >= 1
                AND tlo.is_active = 'Yes'
                AND ui.is_active = 'Yes'
            ORDER BY tlo.investor_id, tlo.liabilities_id, tlo.data_date DESC, tlo.liabilities_outstanding_id DESC
        ", [$investorId, $latestDate]);
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
        return DB::select("
            SELECT DISTINCT ON (tlo.investor_id, tlo.liabilities_id)
                tlo.liabilities_id,
                tlo.liabilities_name,
                tlo.currency,
                tlo.outstanding_balance,
                tlo.outstanding_balance_idr,
                tlo.collectability_cif_name,
                tlo.data_date,
                tse.rate
            FROM t_liabilities_outstanding as tlo
            JOIN u_investors ui ON tlo.investor_id = ui.investor_id
            LEFT JOIN t_stg_exchange_rates as tse ON tlo.currency = tse.currency AND tlo.outstanding_date = tse.date
            WHERE tlo.investor_id = ?
                AND tlo.outstanding_date = ?
                AND (LOWER(tlo.liabilities_type) != 'card' OR tlo.liabilities_type IS NULL)
                AND tlo.outstanding_balance >= 1
                AND tlo.is_active = 'Yes'
                AND ui.is_active = 'Yes'
            ORDER BY tlo.investor_id, tlo.liabilities_id, tlo.data_date DESC, tlo.liabilities_outstanding_id DESC
        ", [$investorId, $latestDate]);
    }

    public function totalLiability($investorId, $latestDate)
    {
        $query = DB::selectOne("
            SELECT 
                SUM(outstanding_balance) as total_outstanding_balance
            FROM (
                SELECT DISTINCT ON (tlo.investor_id, tlo.liabilities_id)
                    tlo.outstanding_balance
                FROM t_liabilities_outstanding as tlo
                JOIN u_investors ui ON tlo.investor_id = ui.investor_id
                WHERE tlo.investor_id = ?
                    AND tlo.outstanding_date = ?
                    AND tlo.is_active = 'Yes'
                    AND ui.is_active = 'Yes'
                ORDER BY tlo.investor_id, tlo.liabilities_id, tlo.data_date DESC, tlo.liabilities_outstanding_id DESC
            ) as filtered
        ", [$investorId, $latestDate]);

        return $query->total_outstanding_balance ?? 0;
    }
}