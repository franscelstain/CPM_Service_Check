<?php

namespace App\Http\Controllers\SA\UI;

use App\Models\SA\UI\Button;
use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class ButtonsController extends AppController
{
    public $table = 'SA\UI\Button';
    
    public function index()
    {
        return $this->db_result();
    }
    
    public function action()
    {
        $btn    = ['act_dt_n' => [], 'act_dt_y' => []];
        $data   = Button::where('is_active', 'Yes')->orderBy('action_per_data')->orderBy('sequence_to')->get();
        foreach ($data as $dt)
        {
            $act = $dt->action_per_data == 'Yes' ? 'y' : 'n';
            $btn['act_dt_'.$act][$dt->button_id] = ['action' => $dt->action, 'icon' => $dt->icon, 'name' => $dt->button_name];
        }
        return $this->app_response('Button', $btn);
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