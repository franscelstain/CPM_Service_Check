<?php

namespace App\Http\Controllers\SA\Reference\KYC;

use App\Http\Controllers\AppController;
use App\Models\SA\Reference\Group;
use App\Models\SA\Reference\KYC\DocumentType;
use Illuminate\Http\Request;

class DocumentTypeController extends AppController
{
    public $table = 'SA\Reference\KYC\DocumentType';

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
        $request->request->add(['show_expired' => !empty($request->input('show_expired')) ? $request->input('show_expired') : 'No']);
        return $this->db_save($request, $id);
    }
    
    public function ws_data(Request $request)
    {
        try
        {
            $insert = $update = 0;
            $data   = [];
            $grp    = Group::where([['group_name', 'IDType'], ['is_active', 'Yes']])->first();
            if (!empty($grp->group_id))
            {
                $api    = $this->api_ws(['sn' => 'ReferenceGroupDetail', 'val' => [$grp->ext_code]])->original['data'];
                foreach ($api as $a)
                {                
                    $qry    = DocumentType::where([['doctype_code', $a->code]])->first();
                    $id     = !empty($qry->doctype_id) ? $qry->doctype_id : null;
                    $request->request->add([
                        'doctype_name'  => $a->name,
                        'doctype_code'  => $a->code,
                        'ext_code'      => $a->id,
                        'is_data'       => !empty($id) ? $qry->is_data : 'WS',
                        '__update'      => !empty($id) ? 'Yes' : ''
                    ]);
                    $this->db_save($request, $id, ['validate' => true]);

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