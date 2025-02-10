<?php

namespace App\Imports\Users\Investor;

use App\Models\Users\Investor\CardPriority;
use Maatwebsite\Excel\Concerns\ToModel;
use Auth;

class CardPriorityImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new CardPriority([
            'investor_id'   => $row[0],
            'is_priority'   => $row[1],
            'pre_approve'   => $row[2]
        ]);
    }
}
