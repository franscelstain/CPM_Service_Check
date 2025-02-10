<?php

namespace App\Imports\SA\Reference\KYC;

use App\Models\SA\Reference\KYC\Region;
use Maatwebsite\Excel\Concerns\ToModel;
use Auth;

class RegionImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Region([
            'RegionCode' => $row[0],
            'RegionName' => $row[1]
        ]);
    }
}
