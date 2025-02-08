<?php

namespace App\Imports\SA\Reference\KYC;

use App\Models\SA\Reference\KYC\Holiday;
use Maatwebsite\Excel\Concerns\ToModel;
use Auth;

class HolidayImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Holiday([
            'currency_id'       => $row[0],
            'effective_date'    => $row[1],
            'description'       => $row[2]
        ]);
    }
}
