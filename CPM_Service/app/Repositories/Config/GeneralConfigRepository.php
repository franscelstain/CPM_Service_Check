<?php

namespace App\Repositories\Config;

use Illuminate\Support\Facades\DB;

class GeneralConfigRepository
{
    public function passwordRule() {
        return DB::table('c_config')
                ->where('config_type', 'Password')
                ->where('is_active', 'Yes')
                ->pluck('config_value', 'config_name')
                ->toArray();
    }
}
