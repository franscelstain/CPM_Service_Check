<?php

namespace App\Http\Controllers\SA\Assets\Products;

use App\Http\Controllers\AppController;
use App\Imports\SA\Assets\Products\PriceImport;
use App\Interfaces\Products\PriceRepositoryInterface;
use App\Models\SA\Assets\AssetClass;
use App\Models\SA\Assets\Products\Product;
use App\Models\SA\Assets\Products\Price;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Storage;

class PriceController extends AppController
{
    public $table = 'SA\Assets\Products\Price';
    protected $priceRepo;

    public function __construct(PriceRepositoryInterface $priceRepo)
    {
        $this->priceRepo = $priceRepo;
    }

    public function index(Request $request)  
    {
        try
        {
            $page   = !empty($request->page) ? $request->page : 1;
            $search = !empty($request->search) ? $request->search : 1;
            $data   = Price::select('m_products_prices.*', 'b.product_name')
                    ->join('m_products as b', 'm_products_prices.product_id', '=', 'b.product_id')
                    ->where([['m_products_prices.is_active', 'Yes'], ['b.is_active', 'Yes']]);
            
            if (!empty($request->search))
                $data = $data->where('b.product_name', 'like', '%'. $request->search .'%');
            if (!empty($request->price_date))
                $data = $data->where('price_date', $request->price_date);
            if (!empty($request->price_value))
                $data = $data->where('price_value', 'like', '%'. $request->price_value .'%');
            
            return $this->app_response('Success get data', $data->paginate(10, ['*'], 'page', $page));
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
    
    public function generate()
    {
        try
        {
			$json 	= [];
            $data   = Product::where('is_active', 'Yes')->orderBy('product_id')->get();
            if ($data->count() > 0)
            {
                foreach ($data as $dt)
                {
                    $data2 = Price::where([['product_id', $dt->product_id], ['is_active', 'Yes']])->orderBy('price_date')->get();
                    foreach ($data2 as $dt2)
                    {
                        $json[$dt->product_id][$dt2->price_date] = $dt2->price_value;
                    }
                }
            }
            Storage::disk('local')->put('generate/price.json', json_encode($json));
            return $this->app_response('Generate Price', $json);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function benchmark()
    {
        try
        {
            $json   = [];
            $data   = Product::join('m_asset_class as b', 'm_products.asset_class_id', '=', 'b.asset_class_id')
                    ->join('m_asset_categories as c', 'b.asset_category_id', '=', 'c.asset_category_id')
                    ->where([['m_products.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes'], ['c.asset_category_name', 'Index']])
                    ->orderBy('m_products.product_id')
                    ->get();
            if ($data->count() > 0)
            {
                foreach ($data as $dt)
                {
                    $data2 = Price::where([['product_id', $dt->product_id], ['is_active', 'Yes']])->orderBy('price_date')->get();
                    foreach ($data2 as $dt2)
                    {
                        $json[$dt->product_id][$dt2->price_date] = $dt2->price_value;
                    }
                }
            }
            Storage::disk('local')->put('generate/benchmark.json', json_encode($json));
            return $this->app_response('Benchmark Price', $json);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
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
            
            $excel  = Excel::toArray(new PriceImport, storage_path('import') .'/'. $file->getClientOriginalName());
            $no = 1;            
            foreach ($excel[0] as $ex)
            {
                if ($no > 0)
                {
                    $prd = Product::where([['product_code', $ex[0]], ['is_active', 'Yes']])->first();
                    if (!empty($prd->product_id))
                    {
                        $date   = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($ex[1]);
                        $data   = [
                                    'product_id'    => $prd->product_id, 
                                    'price_date'    => \Carbon\Carbon::instance($date), 
                                    'price_value'   => $ex[2],
                                    'created_by'    => $usrtyp.':'.$usrid.':'.$usrnm,
                                    'created_host'  => $ip
                                ];
                        if ($qry = Price::create($data))
                        {
                            array_push($details, ['id' => $qry->price_id]);
                            $success++;
                        }
                        else
                        {
                            $fail++;
                        }
                    }
                }
                $no++;
            }

            unlink(storage_path('import') .'/'. $file->getClientOriginalName());
            return $this->app_partials($success, $fail, $details);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function listData(Request $request)
    {
        try {
            $filters = [
                'search' => $request->input('search'),
                'price_date' => $request->input('price_date'), // Tambahkan filter price_date
                'price_value' => $request->input('price_value'), // Tambahkan filter price_value
            ];
            $limit = $request->input('limit', 10); // Default limit 10
            $colName = $request->input('colName', 'product_name');
            $colSort = $request->input('colSort', 'asc');
            $page = $request->input('page');
            $price = $this->priceRepo->listData($filters, $limit, $page, $colName, $colSort);
            $total = !empty($search) ? $this->priceRepo->countProduct() : $price->total();
    
            return $this->app_response('Products - Price', [
                'item' => $price->items(),
                'current_page' => $price->currentPage(),
                'last_page' => $price->lastPage(),
                'per_page' => $price->perPage(),
                'total' => $total,
                'total_filtered' => $price->total(), // Adding filtered total
            ]);    
        } catch (\Exception $e) {
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
            ini_set('max_execution_time', '14400');
            
            $data   = [];
            $date   =  date('Y-m-d');

            for ($i = 1; $i <= 15; $i++)
            {
                $insert = [];
                $update = [];
                $date = date('Y-m-d', strtotime('-'.$i.' days'));
                $api = $this->api_ws(['sn' => 'MarketPriceMF', 'val' => [$date]])->original['data'];
                foreach ($api as $a)
                {
                    $prd = Product::where([['ext_code', $a->productCode], ['is_active', 'Yes']])->first();
                    if (!empty($prd->product_id))
                    {
                        $productId  = $prd->product_id;
                        $priceDate  = $a->date;
                        $priceValue = $a->value;

                        $existingRow = DB::table('m_products_prices')
                            ->where('product_id', $productId)
                            ->where('price_date', $priceDate)
                            ->first();

                        $isExisting = !empty($existingRow);

                        DB::table('m_products_prices')->updateOrInsert(
                            [
                                'product_id' => $productId,
                                'price_date' => $priceDate,
                            ],
                            [
                                'price_value' => $priceValue,
                                'updated_at'  => Carbon::now(),
                                'created_at'  => $isExisting ? $existingRow->created_at : Carbon::now(),
                                'is_active' => 'Yes',
                                'created_by' => 'system',
                                'created_host' => 'localhost'
                            ]
                        );

                        if ($isExisting) {
                            $update[] = $a;
                        } else {
                            $insert[] = $a;
                        }
                    }
                   
                }

                $data[$i] = ['insert' => $insert, 'update' => $update];
                 // $date = date('Y-m-d', strtotime('-'.$i.' days'));

            }
            // return $this->app_partials($insert+$update, 0, ['save' => ['data' => $data]]);
            return $this->app_response('data', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function get_ws_price_all(Request $request)
    {
        try
        {
            ini_set('max_execution_time', '14400');
            $insert = [];
            $update = [];
            $api = $this->api_ws(['sn' => 'MarketPriceMFAll'])->original['data'];
            foreach ($api as $a)
            {
                $prd = Product::where([['ext_code', $a->productCode], ['is_active', 'Yes']])->first();
                if (!empty($prd->product_id))
                {
                    $productId  = $prd->product_id;
                    $priceDate  = $a->date;
                    $priceValue = $a->value;

                    $existingRow = DB::table('m_products_prices')
                        ->where('product_id', $productId)
                        ->where('price_date', $priceDate)
                        ->first();

                    $isExisting = !empty($existingRow);

                    DB::table('m_products_prices')->updateOrInsert(
                        [
                            'product_id' => $productId,
                            'price_date' => $priceDate,
                        ],
                        [
                            'price_value' => $priceValue,
                            'updated_at'  => Carbon::now(),
                            'created_at'  => $isExisting ? $existingRow->created_at : Carbon::now(),
                            'is_active' => 'Yes',
                            'created_by' => 'system',
                            'created_host' => 'localhost'
                        ]
                    );

                    if ($isExisting) {
                        $update[] = $a;
                    } else {
                        $insert[] = $a;
                    }
                }
            }
            return $this->app_partials($insert+$update, 0, ['save' => ['insert' => $insert, 'update' => $update]]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function get_ws_price_product_last(Request $request)
    {
        try
        {
            ini_set('max_execution_time', '14400');
            // $insert = $update = 0;
            $data   = [];
            $insert = [];
            $update = [];
            $api = $this->api_ws(['sn' => 'MarketPriceMFAll'])->original['data'];$insert = $update = [];

            foreach ($api as $a)
            {
                $date = date('Y-m-d', strtotime('-1 days'));
                $prd = Product::where([['ext_code', $a->productCode], ['is_active', 'Yes']])->whereDate('created_at', '>=', $date)->orderBy('created_at', 'DESC')->get();

                if (!empty($prd->product_id))
                {
                    $qry    = Price::where([['product_id', $prd->product_id], ['price_date', $a->date]])->first();

                    $productId  = $prd->product_id;
                    $priceDate  = $a->date;
                    $priceValue = $a->value;

                    $existingRow = DB::table('m_products_prices')
                        ->where('product_id', $productId)
                        ->where('price_date', $priceDate)
                        ->first();

                    $isExisting = !empty($existingRow);
                    
                    DB::table('m_products_prices')->updateOrInsert(
                        [
                            'product_id' => $productId,
                            'price_date' => $priceDate,
                        ],
                        [
                            'price_value' => $priceValue,
                            'updated_at'  => Carbon::now(),
                            'created_at'  => $isExisting ? $existingRow->created_at : Carbon::now(),
                            'is_active' => 'Yes',
                            'created_by' => 'system',
                            'created_host' => 'localhost'
                        ]
                    );

                    if ($isExisting) {
                        $update[] = $a;
                    } else {
                        $insert[] = $a;
                    }
                }
            }

            $data = ['insert' => $insert, 'update' => $update];
            return $this->app_response('data', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}