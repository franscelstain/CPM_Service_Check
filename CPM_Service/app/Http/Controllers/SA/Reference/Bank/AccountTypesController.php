<?php

namespace App\Http\Controllers\SA\Reference\Bank;

use App\Http\Controllers\AppController;
use App\Models\SA\Reference\Bank\AccountType;
use App\Models\SA\Reference\Group;
use Illuminate\Http\Request;

class AccountTypesController extends AppController
{
    public $table = 'SA\Reference\Bank\AccountType';

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
            $grp    = Group::where([['group_name', 'BankAccountType'], ['is_active', 'Yes']])->first();
            if (!empty($grp->group_id))
            {
                $api = $this->api_ws(['sn' => 'ReferenceGroupDetail', 'val' => [$grp->ext_code]])->original['data'];
                foreach ($api as $a)
                {
                    $qry    = AccountType::where([['ext_code', $a->id]])->first();
                    $id     = !empty($qry->account_type_id) ? $qry->account_type_id : null;
                    $request->request->add([
                        'account_type_name' => $a->name,
                        'account_type_code' => $a->code,
                        'ext_code'          => $a->id,
                        'is_data'           => !empty($id) ? $qry->is_data : 'WS',
                        '__update'          => !empty($id) ? 'Yes' : ''
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