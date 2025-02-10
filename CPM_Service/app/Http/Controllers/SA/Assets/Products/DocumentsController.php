<?php

namespace App\Http\Controllers\SA\Assets\Products;

use App\Models\SA\Assets\AssetDocument;
use App\Models\SA\Assets\Products\Document;
use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class DocumentsController extends AppController
{
    public $table = 'SA\Assets\Products\Document';

    public function index()
    {
        $filter = [
            'join' => [
                ['tbl' => 'm_products', 'key' => 'product_id', 'select' => ['product_name']],
                ['tbl' => 'm_asset_documents', 'key' => 'asset_document_id', 'select' => ['asset_document_name']],
            ]
        ];
        return $this->db_result($filter);
    }
    
    public function category(Request $request)
    {
        $data = AssetDocument::select('m_asset_documents.*')
                ->join('m_asset_class as b', 'm_asset_documents.asset_category_id', '=', 'b.asset_category_id')
                ->join('m_products as c', 'b.asset_class_id', '=', 'c.asset_class_id')
                ->where([['c.product_id', $request->product_id], ['m_asset_documents.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                ->whereNotIn('m_asset_documents.asset_document_id', function($q) use ($request) { $q->select('d.asset_document_id')->from('m_products_documents as d')->where([['d.product_id', $request->product_id], ['d.is_active', 'Yes']]); })
                ->get();
        return $this->app_response('Document Category', $data);
    }
    
    public function category_detail(Request $request)
    {
        $data = Document::select('m_products_documents.*', 'b.*', 'c.product_name')
                ->join('m_products as c', 'm_products_documents.product_id', '=', 'c.product_id')
                ->join('m_asset_documents as b', 'b.asset_document_id', '=', 'm_products_documents.asset_document_id')
                ->where([['m_products_documents.document_id', $request->document_id]])
                ->get();
        return $this->app_response('Document Categorys', $data);
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