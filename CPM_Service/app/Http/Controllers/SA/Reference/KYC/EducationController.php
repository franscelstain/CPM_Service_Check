<?php

namespace App\Http\Controllers\SA\Reference\KYC;

use App\Http\Controllers\AppController;
use App\Models\SA\Reference\Group;
use App\Models\SA\Reference\KYC\Education;
use Illuminate\Http\Request;

class EducationController extends AppController
{
    public $table = 'SA\Reference\KYC\Education';

    public function index()
    {
        return $this->db_result();
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id);
    }
    
    public function ws_data(Request $request)
    {
        try
        {
            $insert = $update = 0;
            $data   = [];
            $grp    = Group::where([['group_name', 'Education'], ['is_active', 'Yes']])->first();
            if (!empty($grp->group_id))
            {
                $api = $this->api_ws(['sn' => 'ReferenceGroupDetail', 'val' => [$grp->ext_code]])->original['data'];
                foreach ($api as $a)
                {                
                    $qry    = Education::where([['ext_code', $a->code]])->first();
                    $id     = !empty($qry->education_id) ? $qry->education_id : null;
                    $request->request->add([
                        'education_name'    => $a->name,
                        'ext_code'          => $a->code,
                        'is_data'           => !empty($id) ? $qry->is_data : 'WS',
                        '__update'          => !empty($id) ? 'Yes' : ''
                    ]);
                    $this->db_save($request, $id);

                    if (empty($id))
                        $insert++;
                    else
                        $update++;
                }
            }
            return $this->app_partials($insert+$update, 0, ['save' => ['insert' => $insert, 'update' => $update]]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}