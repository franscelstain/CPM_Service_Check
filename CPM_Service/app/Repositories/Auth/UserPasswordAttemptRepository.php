<?php

namespace App\Repositories\Auth;

use App\Models\Users\UserPasswordAttemp;
use Carbon\Carbon;

class UserPasswordAttemptRepository
{
    public function getByUserId($userId) {
        return UserPasswordAttemp::where('user_id', $userId)
            ->where('is_active', 'Yes')
            ->first();
    }

    public function incrementAttempt($userId): int
    {
        $record = $this->getByUserId($userId);
        $count = $record ? $record->attempt_count + 1 : 1;

        if (!$record) {
            UserPasswordAttemp::create([
                'user_id' => $userId,
                'attempt_count' => $count,
                'is_active' => 'Yes',
                'created_by' => 'System',
                'created_host' => '127.0.0.1',
                'created_at' => Carbon::now()
            ]);
        } else {
            $record->update([
                'attempt_count' => $count,
                'updated_at' => Carbon::now(),
                'updated_by' => 'System',
                'updated_host' => '127.0.0.1'
            ]);
        }

        return $count;
    }

    public function resetAttempts($userId)
    {
        return UserPasswordAttemp::where('user_id', $userId)
            ->update(['is_active' => 'No']);
    }
}