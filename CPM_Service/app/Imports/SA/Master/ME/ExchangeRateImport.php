<?php

namespace App\Imports\SA\Master\ME;

use App\Models\SA\Master\ME\ExchangeRate;
use Maatwebsite\Excel\Concerns\ToModel;
use Auth;

class ExchangeRateImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new ExchangeRate([
            'exchange_rate_id'  => $row[0],
            'currency_id'       => $row[1],
            'exchange_value'    => $row[2]
        ]);
    }
}
