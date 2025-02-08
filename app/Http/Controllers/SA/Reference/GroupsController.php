<?php

namespace App\Http\Controllers\SA\Reference;

use App\Http\Controllers\AppController;
use App\Models\SA\Reference\Group;
use Illuminate\Http\Request;

class GroupsController extends AppController
{
    public $table = 'SA\Reference\Group';
    
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
            $api    = $this->api_ws(['sn' => 'ReferenceGroup'])->original['data'];
            foreach ($api as $a)
            {                
                $qry    = Group::where('ext_code', strval($a->id))->first();
                $id     = !empty($qry->group_id) ? $qry->group_id : null;
                $request->request->add([
                    'group_name'    => trim($a->group),
                    'ext_code'      => $a->id,
                    'description'   => $a->description,
                    'is_data'       => !empty($id) ? $qry->is_data : 'WS',
                    '__update'      => !empty($id) ? 'Yes' : ''
                ]);

                $this->db_save($request, $id, ['validate' => true]);
                
                if (empty($id))
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