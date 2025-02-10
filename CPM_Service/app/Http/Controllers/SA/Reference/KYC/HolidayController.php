<?php

namespace App\Http\Controllers\SA\Reference\KYC;

use App\Http\Controllers\AppController;
use App\Imports\SA\Reference\KYC\HolidayImport;
use App\Models\SA\Assets\Products\Currency;
use App\Models\SA\Reference\KYC\Holiday;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class HolidayController extends AppController
{
    public $table = 'SA\Reference\KYC\Holiday';

    public function index()
    {
        return $this->db_result();
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }
    
    public function import(Request $request)
    {   
        try
        {
            if (!empty($this->app_validate($request, ['file_import' => 'required|max:2048|mimes:xls,xlsx'])))
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
            $excel  = Excel::toArray(new HolidayImport, storage_path('import') .'/'. $file->getClientOriginalName());
            foreach ($excel[0] as $ex)
            {
                if ($n > 0)
                {
                    $curr = Currency::where([['currency_code', $ex[0]], ['is_active', 'Yes']])->first();
                    if (!empty($curr->currency_id) && !empty($ex[1]))
                    {
                        $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($ex[1]);
                        $data = [
                            'currency_id'       => $curr->currency_id, 
                            'effective_date'    => \Carbon\Carbon::instance($date), 
                            'description'       => $ex[2],
                            'created_by'        => $usrtyp.':'.$usrid.':'.$usrnm,
                            'created_host'      => $ip
                        ];
                        if ($qry = Holiday::create($data))
                        {
                            array_push($details, ['id' => $qry->holiday_id]);
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
            $api    = $this->api_ws(['sn' => 'Holiday'])->original['data'];
            foreach ($api as $a)
            {
                $curr   = Currency::where([['currency_code', $a->currency], ['is_active', 'Yes']])->first();
                $qry    = Holiday::where([['ext_code', $a->id]])->first();
                $id     = !empty($qry->holiday_id) ? $qry->holiday_id : null;
                $request->request->add([
                    'currency_id'       => !empty($curr->currency_id) ? $curr->currency_id : null,
                    'effective_date'    => $a->date,
                    'description'       => $a->description,
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
            return $this->app_partials($insert+$update, 0, ['save' => ['insert' => $insert, 'update' => $update]]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}