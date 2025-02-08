<?php

namespace App\Http\Controllers\SA\Master\FinancialCheckUp;

use App\Http\Controllers\AppController;
use App\Traits\Financial\Condition\FinancialRatio;
use Illuminate\Http\Request;

class RatioController extends AppController
{
    use FinancialRatio;

    public $table = 'SA\Master\FinancialCheckUp\Ratio';

    public function index()
    {
        return $this->db_result();
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function published()
    {
        $filter = [
            'order' => ['sequence_to' => 'asc'],
            'where' => [['effective_date', '<=', $this->app_date()], ['published', 'Yes']]
        ];
        return $this->db_result($filter);
    }

    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id);
    }
}