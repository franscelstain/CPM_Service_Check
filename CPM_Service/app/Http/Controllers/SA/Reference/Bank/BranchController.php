<?php

namespace App\Http\Controllers\SA\Reference\Bank;

use App\Http\Controllers\AppController;
use App\Models\SA\Reference\Bank\Branch;
use App\Models\SA\Reference\Group;
use Illuminate\Http\Request;

class BranchController extends AppController
{
    public $table = 'SA\Reference\Bank\Branch';

    public function index()
    {
        $filter = ['join' => [
            ['tbl' => 'm_bank', 'key' => 'bank_id', 'select' => ['bank_name']]
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
    
    public function ws_data(Request $request)
    {
        try
        {
            $insert = $update = 0;
            $data   = [];
            $grp    = Group::where([['group_name', 'BankBranch'], ['is_active', 'Yes']])->first();
            if (!empty($grp->group_id))
            {
                $api = $this->api_ws(['sn' => 'ReferenceGroupDetail', 'val' => [$grp->ext_code]])->original['data'];
                foreach ($api as $a)
                {
                    $qry    = Branch::where([['ext_code', $a->id]])->first();
                    $id     = !empty($qry->bank_branch_id) ? $qry->bank_branch_id : null;
                    $request->request->add([
                        'branch_name'   => $a->name,
                        'branch_code'   => $a->code,
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