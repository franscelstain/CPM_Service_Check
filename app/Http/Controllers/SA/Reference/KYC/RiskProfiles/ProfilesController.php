<?php

namespace App\Http\Controllers\SA\Reference\KYC\RiskProfiles;

use App\Http\Controllers\AppController;
use App\Models\SA\Reference\KYC\RiskProfiles\Profile;
use Illuminate\Http\Request;

class ProfilesController extends AppController
{
    public $table = 'SA\Reference\KYC\RiskProfiles\Profile';

    public function index()
    {
        return $this->db_result();
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }
    
    protected function form_ele()
    {
        return ['path' => 'riskprofiles/img'];
    }

    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id, $this->form_ele());
    }
    
    public function ws_data(Request $request)
    {
        try
        {
            $insert = $update = 0;
            $data   = [];
            $api    = $this->api_ws(['sn' => 'RiskProfile'])->original['data'];

	    foreach ($api as $a)
            {                
                $qry    = Profile::where([['ext_code', $a->id]])->first();

                $id     = !empty($qry->profile_id) ? $qry->profile_id : null;
                $request->request->add([
                    'profile_name'  => $a->name,
                    'min'           => !empty($a->minValue) ? $a->minValue : 0,
                    'max'           => $a->maxValue,
                    'ext_code'      => $a->id,
                    'sequence_to'   => $a->no,
                    'description'   => $a->description,
                    'is_data'       => !empty($id) ? $qry->is_data : 'WS',
                    '__update'      => !empty($id) ? 'Yes' : ''
                ]);
                $this->db_save($request, $id, ['validate' => true]);
                
                if (empty($qry->profile_id))
                    $insert++;
                else
                    $update++;
            }
            return $this->app_partials($insert+$update, 0, ['save' => ['insert' => $insert, 'update' => $update]]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}