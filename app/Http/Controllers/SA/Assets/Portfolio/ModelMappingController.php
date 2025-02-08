<?php

namespace App\Http\Controllers\SA\Assets\Portfolio;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\Portfolio\Models;
use App\Models\SA\Reference\KYC\RiskProfiles;
use Illuminate\Http\Request;

class ModelMappingController extends AppController
{
    public $table = 'SA\Assets\Portfolio\ModelMapping';

    public function index()
    {
        $filter = ['join' => [ 
            ['tbl' => 'm_models', 'key' => 'model_id', 'select' => ['model_name']],
            ['tbl' => 'm_risk_profiles', 'key' => 'profile_id', 'select' => ['profile_name']]
        ]];
        return $this->db_result($filter);
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id);
    }
}