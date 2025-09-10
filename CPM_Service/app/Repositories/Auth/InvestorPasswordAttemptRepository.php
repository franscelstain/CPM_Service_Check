<?php

namespace App\Repositories\Auth;

use App\Models\Users\Investor\InvestorPasswordAttemp;
use Carbon\Carbon;

class InvestorPasswordAttemptRepository
{
    public function getByInvestorId($investorId) {
        return InvestorPasswordAttemp::where('investor_id', $investorId)
            ->where('is_active', 'Yes')
            ->first();
    }

    public function incrementAttempt($investorId): int
    {
        $record = $this->getByInvestorId($investorId);
        $count = $record ? $record->attempt_count + 1 : 1;

        if (!$record) {
            InvestorPasswordAttemp::create([
                'investor_id' => $investorId,
                'attempt_count' => $count,
                'is_active' => 'Yes',
                'created_by' => 'System',
                'created_host' => '127.0.0.1',
                'created_at' => Carbon::now()
            ]);
        } else {
            $record->update([
                'attempt_count' => $count,
                'attempt_count' => $count,
                'updated_at' => Carbon::now(),
                'updated_by' => 'System',
                'updated_host' => '127.0.0.1'
            ]);
        }

        return $count;
    }
}