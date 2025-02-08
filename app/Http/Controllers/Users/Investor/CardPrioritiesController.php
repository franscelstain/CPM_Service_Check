<?php

namespace App\Http\Controllers\Users\Investor;

use App\Http\Controllers\AppController;
use App\Imports\Users\Investor\CardPriorityImport;
use App\Models\Users\Investor\CardPriority;
use App\Models\Users\Investor\Investor;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use DB;

class CardPrioritiesController extends AppController
{
    public $table = 'Users\Investor\CardPriority';

    public function index()
    {
    	try
        {
            $data   = DB::table('u_investors as ui')
                    ->join('u_investors_card_priorities as uicp', 'ui.cif', '=', 'uicp.cif')
                    ->where([['ui.is_active', 'Yes'], ['uicp.is_active', 'Yes']])
                    ->select('ui.investor_id', 'ui.cif', 'uicp.card_expired', DB::raw('ui.fullname as investor_name'), 
                            DB::raw("CASE WHEN uicp.is_priority IS TRUE THEN 'Priority' ELSE 'Non Priority' END category"),
                            DB::raw("CASE WHEN uicp.pre_approve IS TRUE THEN 'Yes' ELSE 'No' END pre_approve"))
                    ->distinct()
                    ->get();
			return $this->app_response('Investor Priority Card', $data);        
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function detail($id)
    {
        try
        {
            $data   = DB::table('u_investors as ui')
                    ->join('u_investors_card_priorities as uicp', 'ui.cif', '=', 'uicp.cif')
                    ->join('u_investors_card_types as uict', 'uicp.investor_card_type_id', '=', 'uict.investor_card_type_id')
                    ->where([['ui.investor_id', $id], ['ui.is_active', 'Yes'], ['uicp.is_active', 'Yes'], ['uict.is_active', 'Yes']])
                    ->select('ui.investor_id', 'ui.fullname', 'ui.cif', 'uicp.card_expired', 'uicp.is_priority', 
                            'uicp.pre_approve', 'uict.card_type_name')
                    ->first();
            return $this->app_response('Investor Priority Card Detail', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function import(Request $request)
    {   
        try
        {
            if (!empty($this->app_validate($request, ['file_import' => 'required|max:2048|mimes:csv,xls,xlsx'])))
            {
                exit();
            }
            
            $fail       = $success = 0;
            $details    = [];
            $usrtyp     = !empty($this->auth_user()) ? $this->auth_user()->usercategory_name : 'Visitor';
            $usrnm      = !empty($this->auth_user()) ? $this->auth_user()->fullname : 'User';
            $usrid      = !empty($this->auth_user()) ? $this->auth_user()->id : 0;
            $ip         = !empty($request->input('ip')) ? $request->input('ip') : $request->ip();
            $file       = $request->file('file_import');
            
            $file->move(storage_path('import'), $file->getClientOriginalName());
            
            $n      = 0;
            $excel  = Excel::toArray(new CardPriorityImport, storage_path('import') .'/'. $file->getClientOriginalName());
            foreach ($excel[0] as $ex)
            {
                if ($n > 0)
                {
                    $inv  = Investor::where([['cif', $ex[0]], ['is_active', 'Yes']])->first();
                    if (!empty($inv->investor_id))
                    {
                        $data = [
                            'investor_id'       => !empty($inv->investor_id) ? $inv->investor_id : null, 
                            'is_priority'       => $ex[1],
                            'pre_approve'       => $ex[2],
                            'created_by'        => $usrtyp.':'.$usrid.':'.$usrnm,
                            'created_host'      => $ip
                        ];
                        if ($qry = CardPriority::create($data))
                        {
                            array_push($details, ['id' => $qry->investor_id]);
                            $success++;
                        }
                        else
                        {
                            $fail++;
                        }
                    }
                }
                $n++;
            }
            
            unlink(storage_path('import') .'/'. $file->getClientOriginalName());
            return $this->app_partials($success, $fail, $details);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}