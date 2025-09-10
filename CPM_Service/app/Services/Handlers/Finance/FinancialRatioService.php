<?php

namespace App\Services\Handlers\Finance;

use App\Models\SA\Master\FinancialCheckUp\Ratio;
use App\Interfaces\Finance\FinancialRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class FinancialRatioService
{
    protected $financialRepository;

    public function __construct(FinancialRepositoryInterface $financialRepository)
    {
        $this->financialRepository = $financialRepository;
    }

    public function calculateFinancialScore(array $summary, object $investor): float
    {
        $total = [
            'assets-liabilities' => (object)[
                'assets' => $summary['assets'] ?? 0,
                'liabilities' => $summary['liabilities'] ?? 0,
            ],
            'income-expense' => (object)[
                'income' => $summary['income'] ?? 0,
                'expense' => $summary['expense'] ?? 0,
            ],
            'insurance' => 0,
            'saving' => 0,
        ];

        $ratios = $this->getEffectiveRatios();

        $needInsurance = $ratios->contains('ratio_method', 'InsuranceCover');
        $needSaving    = $ratios->contains('ratio_method', 'SavingToIncomeRatio');

        if ($needInsurance) {
            $total['insurance'] = $this->financialRepository->getAssetTotalByName($investor->investor_id, 'insurance');
        } elseif ($needSaving) {
            $total['saving'] = $this->financialRepository->getAssetTotalByName($investor->investor_id, 'saving');
        }

        $val = 0;

        foreach ($ratios as $ratio) {
            switch ($ratio->ratio_method) {
                case 'DebtToAssetRatio':
                    $res = $total['assets-liabilities']->assets > 0
                        ? $total['assets-liabilities']->liabilities / $total['assets-liabilities']->assets
                        : 0;
                    break;
                case 'DebtToIncome':
                    $res = $total['income-expense']->income > 0
                        ? $total['assets-liabilities']->liabilities / $total['income-expense']->income
                        : 0;
                    break;
                case 'EmergencyRatio':
                    $res = $total['income-expense']->expense > 0
                        ? $total['assets-liabilities']->assets / $total['income-expense']->expense
                        : 0;
                    break;
                case 'InsuranceCover':
                    $res = $total['assets-liabilities']->assets > 0
                        ? $total['insurance'] / $total['assets-liabilities']->assets
                        : 0;
                    break;
                case 'SavingToIncomeRatio':
                    $res = $total['income-expense']->income > 0
                        ? $total['saving'] / $total['income-expense']->income
                        : 0;
                    break;
                default:
                    $res = 0;
                    break;
            }

            $res = $ratio->ratio_type == 'Percent' ? $res * 100 : $res;
            $val += $this->ratioScore($res, $ratio);
        }

        return $ratios->count() > 0 ? (float) $val / $ratios->count() : 0;
    }

    private function getEffectiveRatios()
    {
        return Cache::remember('financial_ratios_today', Carbon::now()->addHour(), function () {
            return Ratio::whereDate('effective_date', '<=', DB::raw('CURRENT_DATE'))
                        ->where('published', 'Yes')
                        ->where('is_active', 'Yes')
                        ->get();
        });
    }

    private function ratioScore($value, $ratio): int
    {
        if ($this->ratioScoreMatched($value, $ratio->perfect_operator, $ratio->perfect_value, $ratio->perfect_value2 ?? null)) {
            return 10;
        }

        if ($this->ratioScoreMatched($value, $ratio->warning_operator, $ratio->warning_value, $ratio->warning_value2 ?? null)) {
            return 5;
        }

        return 0;
    }

    private function ratioScoreMatched($value, $operator, $value1, $value2 = null): bool
    {
        switch ($operator) {
            case 'Equal':
                return $value == $value1;
            case 'Less than':
                return $value < $value1;
            case 'Less than equal to':
                return $value <= $value1;
            case 'Greater than':
                return $value > $value1;
            case 'Greater than equal to':
                return $value >= $value1;
            case 'Between':
                return $value >= $value1 && $value <= $value2;
            default:
                return false;
        }
    }
}
