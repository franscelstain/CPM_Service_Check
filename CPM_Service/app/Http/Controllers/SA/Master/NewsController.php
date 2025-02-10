<?php

namespace App\Http\Controllers\SA\Master;

use App\Http\Controllers\AppController;
use App\Models\SA\Master\News;
use Illuminate\Http\Request;
use Auth;

class NewsController extends AppController
{
    public $table = 'SA\Master\News';

    public function index()
    {
        return $this->db_result();
    }

    public function content(Request $request)
    {
        $pub    = Auth::guard('admin')->check() ? ['Public', 'Internal'] : ['Public'];
        $news   = News::select('m_news.*', 'b.fullname as author')->join('u_users as b', 'm_news.author_id', '=', 'b.user_id')
                ->where([['published_date', '<=', $this->app_date()], ['published', 'Yes'], ['m_news.is_active', 'Yes'], ['b.is_active', 'Yes']])
                ->whereIn('published_to', $pub);
        switch ($request->slug)
        {
            case 'search'   : $news = $news->where('news_title', 'ilike', '%'. $request->search .'%');
            case 'page'     : $news = $news->orderBy('published_date', 'desc')->paginate(6, ['*'], 'page', $request->page); break;
            default         : $news = $news->where('news_slug', $request->slug)->first(); break;
        }
        return $this->app_response('News', $news);
    }

    public function random(Request $request)
    {
        $pub    = Auth::guard('admin')->check() ? ['Public', 'Internal'] : ['Public'];
        $limit  = !empty($request->limit) ? $request->limit : 3;
        $news   = News::select('m_news.*', 'b.fullname as author')->join('u_users as b', 'm_news.author_id', '=', 'b.user_id')
                ->where([['published_date', '<=', $this->app_date()], ['published', 'Yes'], ['m_news.is_active', 'Yes'], ['b.is_active', 'Yes']])
                ->whereIn('published_to', $pub);
        if (!empty($request->filter))
        {
            $news = $news->where('news_slug', '!=', $request->filter);
        }
        return $this->app_response('News', $news->inRandomOrder()->limit($limit)->get());
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function published()
    {
        $filter = ['where' => [['published_date', '<=', $this->app_date()], ['published', 'Yes']]];
        return $this->db_result($filter);
    }

    public function news_list()
    {
      return $this->db_result(['where' => [['published', 'Yes']], 'order' => ['published_date' => 'desc']]);
    }
    

    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id, ['path' => 'news/img']);
    }
}