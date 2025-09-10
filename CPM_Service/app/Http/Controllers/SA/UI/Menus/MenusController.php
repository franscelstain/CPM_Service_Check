<?php

namespace App\Http\Controllers\SA\UI\Menus;

use App\Http\Controllers\AppController;
use App\Models\SA\UI\Menus\Menu;
use App\Models\Users\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MenusController extends AppController
{
    public $table = 'SA\UI\Menus\Menu';
    
    public function index()
    {
        try 
        {
            $menu   = [];
            $data   = Menu::select('m_menus.*', 'b.group_name', 'c.usercategory_name')
                    ->leftJoin('m_menus_groups as b', function ($join) { 
                        $join->on('m_menus.group_id', '=', 'b.group_id')->where('b.is_active', 'Yes'); 
                    })->join('u_users_categories as c', 'm_menus.usercategory_id', '=', 'c.usercategory_id')
                    ->where([['m_menus.is_active', 'Yes'], ['c.is_active', 'Yes']])
                    ->orderBy('m_menus.parent_id')->orderBy('b.sequence_to')->orderBy('m_menus.sequence_to')
                    ->get();
            foreach ($data as $dt)
            {
                $prt_id = $dt->menu_id != $dt->parent_id ? $dt->parent_id : '';
                $menu[] = [
                    'menu_id'           => $dt->menu_id,
                    'parent_id'         => $dt->parent_id,
                    'parent_name'       => $this->parent_detail($prt_id), 
                    'menu_name'         => $dt->menu_name,
                    'usercategory_name' => $dt->usercategory_name, 
                    'group_name'        => $dt->group_name, 
                    'slug'              => $dt->slug,
                    'published'         => $dt->published ? 'Yes' : 'No'
                ];
            }
            return $this->app_response('Menu', ['list' => $menu, 'key' => 'menu_id']);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function breadcrumbs(Request $request, $id='', $mn_id='', $mn_nm='', $mn_btn='', $bc=[])
    {
        try 
        {
            if (!empty($id) || (!empty($request->category) && !empty($request->slug)))
            {
                $filter = empty($id) ? [['usercategory_id', $request->category], ['slug', $request->slug]] : ['menu_id' => $id];
                $menu   = Menu::where('is_active', 'Yes')->where($filter)->first();
                if (!empty($menu->menu_id))
                {
                    $mn_id  = empty($id) ? $menu->menu_id : $mn_id;
                    $mn_nm  = empty($id) ? $menu->menu_name : $mn_nm;
                    $mn_btn = empty($id) ? $menu->button : $mn_btn;
                    $bc     = array_merge([$menu->menu_name => $menu->slug], $bc);
                    if (!empty($menu->parent_id))
                    {
                        return $this->breadcrumbs($request, $menu->parent_id, $mn_id, $mn_nm, $mn_btn, $bc);
                    }
                }
            }
            return $this->app_response('Breadcrumbs', ['id' => $mn_id, 'name' => $mn_nm, 'bc' => $bc, 'button' => $mn_btn]);
        } 
        catch (\Exception $e) 
        {
            return $this->app_catch($e);
        }
    }
    
    public function detail($id)
    {
        return $this->db_detail($id);
    }
    
    public function parent(Request $request)
    {
        try
        {
            $menu   = [];
            $user   = !empty($request->category) ? $request->category : $this->cpm_auth()->usercategory_id;
            $data   = Menu::select('menu_id', 'parent_id', 'menu_name')
                    ->leftJoin('m_menus_groups as b', function ($join) { 
                        $join->on('m_menus.group_id', '=', 'b.group_id')->where('b.is_active', 'Yes'); 
                    })->join('u_users_categories as c', 'm_menus.usercategory_id', '=', 'c.usercategory_id')
                    ->where([['m_menus.is_active', 'Yes'], ['c.is_active', 'Yes'], ['m_menus.usercategory_id', $user]])
                    ->orderBy('m_menus.parent_id')->orderBy('b.sequence_to')->orderBy('m_menus.sequence_to')
                    ->get();
            foreach ($data as $dt)
            {
                $menu[] = ['parent_id' => $dt->menu_id, 'parent_name' => $this->parent_detail($dt->parent_id, '', $dt->menu_name)];
            }
            return $this->app_response('Parent Menu', $menu);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    private function parent_detail($id='', $res='', $title='')
	{
	    if (!empty($id))
	    {
    		$row	= Menu::find($id);
			$s		= !empty($title) ? '/' . $title : '';
			$res	= !empty($row->menu_name) ? $row->menu_name . $res : $res;
    		return !empty($row->parent_id) ? $this->parent_detail($row->parent_id, '/' . $res, $title) : $res . $s;
	    }
	    else
	    {
	        return !empty($title) ? $title : '*';
	    }
	}
    
    private function parent_menu($usrtyp_id, $prt_id = 0)
    {
        $data   = Menu::select('m_menus.*', 'b.group_name')
                ->leftJoin('m_menus_groups as b', function ($join) { 
                    $join->on('m_menus.group_id', '=', 'b.group_id')->where('b.is_active', 'Yes'); 
                })->where([['m_menus.usercategory_id', $usrtyp_id], ['m_menus.is_active', 'Yes']]);
        $data   = $prt_id > 0 ? $data->where(function ($qry) use ($prt_id) { $qry->where('m_menus.parent_id', $prt_id)
                ->orWhereNull('m_menus.parent_id'); }) : $data->whereNull('m_menus.parent_id');
        return $data->orderBy('m_menus.parent_id')->orderBy('b.sequence_to')->orderBy('m_menus.sequence_to')->get();
    }
    
    public function save(Request $request, $id=null)
    {
        $data = $request->method() != 'POST' ? Menu::where([['menu_id', $id], ['is_active', 'Yes']])->first() : [];
        $this->update_seq($request, $data);
        
        foreach (['published', 'blank_tab'] as $inp)
            $request->request->add([$inp => $request->input($inp) == 'true' ? 't' : 'f']);
        
        return $this->db_save($request, $id);
    }
    
    public function sequence_to(Request $request)
    {
        try
        {
            $parent_id  = isset($request->parent_id) && is_numeric($request->parent_id) ? $request->parent_id : 0;
            $group_id   = isset($request->group_id) && is_numeric($request->group_id) ? $request->group_id : 0;
            $find       = Menu::where([['is_active', 'Yes'], ['usercategory_id', $request->category]]);
            $find       = $parent_id > 0 ? $find->where(function ($qry) use ($parent_id) { $qry->where('parent_id', $parent_id)->orWhereNull('parent_id'); }) : $find->whereNull('parent_id');
            $find       = $group_id > 0 ? $find->where(function ($qry) use ($group_id) { $qry->where('group_id', $group_id)->orWhereNull('group_id'); }) : $find->whereNull('group_id');
            $max        = $find->max('sequence_to');
            $seqTo      = !empty($max) ? $max + 1 : 1;
            return $this->app_response('Seq. To', $seqTo);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    private function slug($menu)
	{
		$slug = [];
		if (!empty($menu))
		{
			foreach ($menu as $key => $mn)
			{
				if (!empty($mn['slug'])) $slug[] = $mn['slug'];
				if (!empty($mn['sub_slug']))
				{
					foreach ($mn['sub_slug'] as $cs)
					{
						$slug[] = $cs;
					}
				}
			}
		}
		return $slug;
	}
    
    private function update_seq($request, $data)
    {   
        $method = $request->method();
        $prt_id = $method != 'DELETE' ? $request->input('parent_id') : $data->parent_id;
        $grp_id = $method != 'DELETE' ? $request->input('group_id') : $data->group_id;
        $user   = $method != 'DELETE' ? $request->input('usercategory_id') : $data->usercategory_id;
        $seq_to = $method == 'DELETE' ? !empty($data->sequence_to) ? $data->sequence_to-1 : 0 : $request->input('sequence_to');
        
        if (in_array($method, ['POST', 'DELETE']) || ($method == 'PUT' && ($data->parent_id != $prt_id || $data->group_id != $grp_id || $data->sequence_to != $seq_to)))
		{
            $parent = Menu::where([['usercategory_id', $user], ['sequence_to', '>=', $seq_to], ['is_active', 'Yes']]);
            $parent = !empty($prt_id) ? $parent->where('parent_id', $prt_id) : $parent->whereNull('parent_id');
            $parent = !empty($grp_id) ? $parent->where('group_id', $grp_id) : $parent->whereNull('group_id');
            $parent = $parent->orderBy('parent_id')->orderBy('sequence_to');
			if ($parent->count() > 0)
			{
				foreach ($parent->get() as $dt)
				{
					$seq_to++;
                    Menu::where('menu_id', $dt->menu_id)->update(['sequence_to' => $seq_to]);
				}
			}
		}
    }

    public function user($id='', $bc=[])
    {
        try
        {
            $category = $this->auth_user()->usercategory_name;
            $cacheKey = "user_menu_{$category}_{$id}_".implode('_', $bc);
            return Cache::remember($cacheKey, 60, function() use ($category, $id, $bc) {
                $menu   = $usrtyp = $type = [];
                $user   = $this->auth_user()->usercategory_id;

                $data   = Menu::select('m_menus.*', 'b.group_name', 'b.sequence_to as group_sequence_to')
                            ->leftJoin('m_menus_groups as b', function ($join) { 
                                $join->on('m_menus.group_id', '=', 'b.group_id')->where('b.is_active', 'Yes'); 
                            })
                            ->where([
                                ['m_menus.is_active', 'Yes'],
                                ['m_menus.published', true]
                            ]);

                if ($category !== 'Super Admin') {
                    $data = $data->where('m_menus.usercategory_id', $user);
                }

                $data = !empty($id) ? $data->where('m_menus.parent_id', $id) : $data->whereNull('m_menus.parent_id');
                $data = $data->orderBy('m_menus.parent_id')
                            ->orderBy('b.sequence_to')
                            ->orderBy('m_menus.sequence_to')
                            ->get();

                foreach ($data as $dt)
                {
                    $arrBc  = array_merge($bc, [$dt->menu_name => $dt->slug]);
                    $child  = $this->user($dt->menu_id, $arrBc);
                    $arrMn  = [
                        'bc'            => $arrBc,
                        'child'         => $child,
                        'group_id'      => $dt->group_id,
                        'group_name'    => $dt->group_name,
                        'icon'          => $dt->icon,
                        'menu_id'       => $dt->menu_id,
                        'menu_name'     => $dt->menu_name,
                        'menu_type'     => $dt->menu_type,
                        'blank_tab'     => $dt->blank_tab,
                        'parent_id'     => $dt->parent_id,
                        'slug'          => $dt->slug,
                        'sub_slug'      => $this->slug($child)
                    ];

                    if (empty($id))
                    {
                        $menu[$dt->usercategory_id][$dt->menu_id] = $arrMn;
                    }
                    else
                    {
                        $menu[$dt->menu_id] = $arrMn;
                    }

                    if (empty($id) && !in_array($dt->usercategory_id, $type)) 
                    { 
                        $find   = Category::where([
                            ['usercategory_id', $dt->usercategory_id],
                            ['is_active', 'Yes']
                        ])->first();

                        $type[] = $dt->usercategory_id;
                        if (!empty($find->usercategory_name)) { $usrtyp[$dt->usercategory_id] = $find->usercategory_name; }
                    }
                }

                return !empty($id) ? $menu : $this->app_response('User Menu', ['menu' => $menu, 'user_type' => $usrtyp, 'user' => $user]);
            });
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

}