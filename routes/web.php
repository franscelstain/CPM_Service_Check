
<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

// API route group
$router->group(['prefix' => 'api'], function() use ($router) {
    $router->group(['middleware' => 'auth'], function() use ($router) {
        include('admin.php');
        include('sales.php');
        $router->group(['middleware' => ['auth_type:Investor', 'is_investor']], function() use ($router) {
            include('investor.php');
        });
        $router->group(['middleware' => 'auth_type:Investor,Sales'], function() use ($router) {
            $router->get('product/benchmark', 'SA\Assets\Products\ProductsController@benchmark');
            $router->get('financial-ratio/published', 'SA\Master\FinancialCheckUp\RatioController@ratio_published');
            $router->post('reports/e-statement/send-mail/{id}', 'Sales\Report\ConsolidatedController@send_mail');
            
            $router->group(['prefix' => 'portfolio'], function() use ($router) {
                $router->group(['prefix' => 'rebalancing'], function() use ($router) {
                    $router->get('{id}', 'Portfolio\RebalancingController@detail');
                    $router->get('/', 'Financial\Planning\GoalsSetupController@rebalancing');
                });

                 /*** Portfolio Installment ***/
                $router->group(['prefix' => 'installment'], function() use ($router) {
                    $router->post('edit/save', 'Portfolio\InstallmentController@edit');
                    $router->get('edit/save', 'Portfolio\InstallmentController@edit');
                    $router->get('detail', 'Portfolio\InstallmentController@detail');
                    $router->get('inactive', 'Portfolio\InstallmentController@inactive');
                    $router->get('/', 'Portfolio\InstallmentController@index');
                    $router->post('checkout/save', 'Portfolio\InstallmentController@checkout_save');
                    //$router->get('checkout/save', 'Portfolio\InstallmentController@checkout_save');
                });

            });
            
            $router->group(['prefix' => 'products-performance'], function() use ($router) {
                $router->get('asset', 'SA\Assets\AssetClassController@index');                
                $router->get('benchmark/{id}', 'SA\Assets\Products\ProductsController@performance_benchmark');
                $router->get('detail/{id}', 'SA\Assets\Products\ProductsController@performance_detail');
                $router->get('detail', 'SA\Assets\Products\ProductsController@performance_detail');
                $router->get('history-performance/{id}', 'SA\Assets\Products\ProductsController@history_performance');
                $router->post('mutual-fund-list', 'SA\Assets\Products\ProductsController@mutual_fund_list');
                $router->get('recomendation', 'SA\Assets\Products\ProductsController@product_recomendation');
                $router->get('score-performance/{id}', 'SA\Assets\Products\ProductsController@product_score_performance');
                $router->get('/', 'SA\Assets\Products\ProductsController@performance');
            });
            
         
        });
        
        $router->group(['middleware' => 'auth_type:Sales,Super Admin'], function() use ($router) {
            $router->get('asset/class/total', 'SA\Assets\AssetClassController@total_assetclass');
            $router->get('campaign/total', 'SA\Campaign\Rewards\CartController@total_campaign');
            $router->get('users/investor/total', 'Users\InvestorController@totalinvestor');
            $router->group(['prefix' => 'asset/products'], function() use ($router) {
                $router->get('issuer/total', 'SA\Assets\Products\IssuerController@total_issuer');
                $router->get('total', 'SA\Assets\Products\ProductsController@total_product');
            });
        });

       $router->group(['prefix' => 'transaction'], function() use ($router) {
            $router->get('all', 'TransactionsController@all');
            $router->get('redeem', 'TransactionsController@redeem');
            $router->get('status', 'TransactionsController@status');
            $router->get('summary', 'TransactionsController@summary');
            $router->get('switching', 'TransactionsController@switching');
            $router->get('other', 'TransactionsController@other');
            $router->get('/', 'TransactionsController@index');
        });
        
        $router->group(['prefix' => 'notification'], function() use ($router) {
            $router->put('batch-notif', 'Administrative\Broker\MessagesController@__notif_batch');
            $router->put('all-read', 'Administrative\Broker\MessagesController@__notif_all_read');
            $router->get('list-notify', 'Administrative\Broker\MessagesController@__notif_bell');
            $router->get('unsent-notify', 'Administrative\Broker\MessagesController@__notif_unsent');
            $router->get('/', 'Administrative\Broker\MessagesController@__notif_user');
        });
        
        $router->get('config/detail/{cfg}', 'Administrative\Config\ConfigController@detail');
        $router->get('news/content', 'SA\Master\NewsController@content');
        $router->get('news/random', 'SA\Master\NewsController@random');
        $router->get('transaction/portfolio-balance', 'TransactionsController@portfolio_balance');        
    });
    
    $router->group(['prefix' => 'auth'], function() use ($router) {
        $router->post('admin/login', 'Auth\AdminController@login');
        $router->group(['prefix' => 'investor'], function() use ($router) {
            $router->post('email/verify', ['as' => 'email.verify', 'uses' => 'Auth\InvestorController@emailVerify']);
            $router->post('login', 'Auth\InvestorController@login');
            $router->post('password/email', 'Auth\InvestorController@password_email');
            $router->post('register', 'Auth\InvestorController@register');
            $router->post('register/check-identity', 'Auth\InvestorController@check_identity');
            $router->put('resend-otp', 'Auth\InvestorController@resend_otp');
            $router->post('sso', 'Auth\InvestorController@sso');
        });
    });

    $router->get('sales/assets-liab', 'Financial\Condition\AssetsLiabilitiesController@list_for_sales');

    $router->group(['prefix' => 'balance'], function () use ($router) {
        $router->get('assets-liabilities/total/{id}', 'Sales\Balance\AssetsLiabilitiesController@totalAssetsLiabilities');
        $router->group(['prefix' => 'asset-outstanding'], function () use ($router) {
            $router->get('bank/{id}', 'Sales\Balance\AssetOutstandingController@listAssetBank');
            $router->get('bonds/{id}', 'Sales\Balance\AssetOutstandingController@listBondsAsset');
            $router->get('category/{id}', 'Sales\Balance\AssetOutstandingController@getAssetCategory');
            $router->get('insurance/{id}', 'Sales\Balance\AssetOutstandingController@listInsuranceAsset');
            $router->get('latest-date/{id}', 'Sales\Balance\AssetOutstandingController@assetLatestDate');
            $router->get('mutual-fund/{id}', 'Sales\Balance\AssetOutstandingController@listMutualFundAsset');
            $router->get('mutual-fund-class/{id}', 'Sales\Balance\AssetOutstandingController@getAssetMutualClass');
            $router->get('return/{id}', 'Sales\Balance\AssetOutstandingController@totalReturnAsset');
        });
        $router->get('liabilities/{id}', 'Sales\Balance\LiabilitiesOutstandingController@listLiability');
    });
    
    /* Broker - Message Publish*/
    $router->group(['prefix' => 'broker-message'], function() use ($router) {
        $router->get('atm-expired/{user}', 'Administrative\Broker\MessagesController@atm_expired');
        $router->get('birthday/{user}', 'Administrative\Broker\MessagesController@birthday');
        $router->get('edd-expired/sales', 'Administrative\Broker\MessagesController@edd_expired');
        //$router->get('notif-managed-nav/{users}', 'Administrative\Broker\MessagesController@managed_nav');
        $router->get('managed-deposito/{users}', 'Administrative\Broker\MessagesController@managed_deposito');
        $router->get('managed-min-aum/{users}', 'Administrative\Broker\MessagesController@min_aum');
        $router->get('performance/{users}', 'Administrative\Broker\MessagesController@performance');
        $router->get('risk-profile-expired/{user}', 'Administrative\Broker\MessagesController@risk_profile_expired');
        $router->get('transactions/{transId}', 'Administrative\Broker\MessagesController@transaction');
 	    $router->get('request-sid/{inv}', 'Administrative\Broker\MessagesController@request_sid');
        $router->get('request-crond-sid', 'Administrative\Broker\MessagesController@request_crond_sid');
        $router->get('send-crond-sid', 'Administrative\Broker\MessagesController@send_crond_sid');
        $router->get('send-sid/{cif}/{sid}/{ifua}', 'Administrative\Broker\MessagesController@send_sid');
        $router->post('send-sid-mf', 'Administrative\Broker\MessagesController@send_sid_mf');  
        $router->get('transaction-notification-reattempt', 'Administrative\Broker\MessagesController@transaction_notification_reattempt');                              
        $router->get('transaction-switching-notification-reattempt', 'Administrative\Broker\MessagesController@transaction_switching_notification_reattempt');                              
        $router->get('send-ifua/{cif}/{ifua}', 'Administrative\Broker\MessagesController@send_ifua');  
    });
    
    $router->group(['prefix' => 'config'], function() use ($router) {
        $router->get('is-email-exist/{id}/{email}', 'Administrative\Config\ConfigController@is_email_exist');
        $router->get('logo/active', 'Administrative\Config\LogoController@logo_active');
        $router->get('password', 'Administrative\Config\GeneralController@password');
        $router->get('speed-marquee', 'Administrative\Config\ConfigController@speed_marquee');
    });

    $router->group(['prefix' => 'cronjob'], function() use ($router) {
        $router->get('asset-outstanding', 'Administrative\Cronjob\AssetOutstandingController@getData');
        $router->get('bancas-outstanding', 'Administrative\Cronjob\BancasOutstandingController@getData');
        $router->get('delete-old-asset-outstanding', 'Administrative\Cronjob\AssetOutstandingController@deleteOldAssetOutstandings');
        $router->get('edd_investor', 'Administrative\Cronjob\InvestorsController@api_edd');
        $router->get('fee-outstanding', 'Administrative\Cronjob\LiabilitiesOutstandingController@fee_outstanding');
        $router->get('investor-valid', 'Administrative\Cronjob\InvestorsController@valid_account');
        $router->get('liabilities-outstanding', 'Administrative\Cronjob\LiabilitiesOutstandingController@getData');
        $router->get('notif-rebalancing', 'Administrative\Notify\InvestorController@notif_rebalancing');
        $router->get('notif-managed-funds', 'Administrative\Broker\InvestorController@managed_funds');
        $router->get('send-message-wms', 'Administrative\Broker\InvestorController@sent_notify');
        $router->get('transaction-histories', 'Administrative\Cronjob\TransactionHistoriesController@getData');
        $router->get('transaction-send-wms', 'Administrative\Cronjob\TransactionHistoriesController@save_wms');
        $router->get('investor-reset-token', 'Administrative\Cronjob\InvestorsController@reset_token');       
        $router->get('transaction-installment', 'Administrative\Cronjob\InstallmentController@getData'); 
        $router->post('price/ws-data', 'SA\Assets\Products\PriceController@get_ws_price_all');
        $router->post('price/single/ws-data', 'SA\Assets\Products\PriceController@ws_data');
        $router->post('product/price/ws-data', 'SA\Assets\Products\PriceController@get_ws_price_product_last');
        $router->get('price/ws-data', 'SA\Assets\Products\PriceController@get_ws_price_all');
		$router->get('price/single/ws-data', 'SA\Assets\Products\PriceController@ws_data');
		$router->get('product/price/ws-data', 'SA\Assets\Products\PriceController@get_ws_price_product_last');
        $router->get('sukuk-middleware', 'Administrative\Cronjob\SukukMiddlewareController@getData');
        $router->group(['prefix' => 'sync'], function() use ($router) {
	        $router->get('investor-sync', 'Administrative\Cronjob\SyncController@investor_sync');
            $router->get('assets', 'Administrative\Cronjob\SyncController@assets');
            $router->post('assets', 'Administrative\Cronjob\SyncController@assets');
            $router->get('crm-investor', 'Administrative\Cronjob\SyncController@crm_investor');
            $router->post('crm-investor', 'Administrative\Cronjob\SyncController@crm_investor');
            $router->get('investor-priority', 'Administrative\Cronjob\SyncController@investor_priority');
            $router->post('investor-priority', 'Administrative\Cronjob\SyncController@investor_priority');
    	    $router->get('investor-bank-account', 'Administrative\Cronjob\SyncController@bank_account');
            $router->get('sales/report/aum_priority', 'Users\SalesController@get_aum_priority');
            $router->get('investor-risk-profile-sync', 'Administrative\Cronjob\SyncController@investor_risk_profile_sync');
            $router->get('sales-detail', 'Administrative\Cronjob\SyncController@sales_detail');
            $router->get('transaction/status/{gid}/{type}/{provider_status}/{provider_remark}/{provider_reference}', 'Administrative\Cronjob\TransactionHistoriesController@update_status_transaction');
            $router->get('update-ifua', 'Administrative\Cronjob\InvestorsController@update_ifua');            
        });
    });

    $router->group(['prefix' => 'email-blast'], function() use ($router) {
        $router->get('e-statement/investor', 'Users\InvestorsController@eStatement');
        $router->post('send-mail/e-statement/{id}', 'Sales\Report\ConsolidatedController@sendMail');
        $router->get('terms-condition/code/{code}', 'Administrative\Service\TermsController@terms_code');
    });

    /*** BEGIN report / kool-report ***/
    $router->group(['prefix' => 'report'], function () use ($router) {
        $router->get('report-header', 'ReportController@report_header');
        $router->get('asnb', 'ReportController@report_asnb');
        $router->get('trans_histories', 'ReportController@trans_histories');
        $router->get('invbalance', 'ReportController@report_invbalance');
    });
    /*** END report ***/  

    $router->group(['prefix' => 'sales'], function () use ($router) {
        $router->group(['prefix' => 'assets-liabilities'], function () use ($router) {
            $router->get('total/{id}', 'Financial\Condition\AssetsLiabilitiesController@total_for_sales');
        });
        $router->get('terms-condition/code/{code}', 'Administrative\Service\TermsController@terms_code');
    }); 
    
    $router->get('admin/news/content', 'SA\Master\NewsController@news_list');
    $router->get('asset/products/price/generate', 'SA\Assets\Products\PriceController@generate');
    $router->get('asset/products/price/benchmark', 'SA\Assets\Products\PriceController@benchmark');
    //list for campaign
    $router->get('campaign/list/{id}', 'SA\Campaign\ReferencesController@list');
    $router->get('campaign/attr_list', 'SA\Campaign\ReferencesController@attr_list');
    $router->get('campaign_ref/{id}', 'SA\Campaign\ReferencesController@get_campaign_ref');
    $router->get('help-center/{slug}', 'Administrative\Service\Center\HelpController@published');
    $router->get('help-center', 'Administrative\Service\Center\HelpController@published');
    $router->get('glossary/info', 'Administrative\Service\GlossariesController@info');
    $router->get('group_asset_class', 'ReportController@group_asset_class');
    $router->get('investorbyroles', 'Sales\InvestorController@investor_by_roles');
    $router->get('landings', 'LandingsController@index');
    $router->get('menu/breadcrumbs', 'SA\UI\Menus\MenusController@breadcrumbs');
    $router->post('otp', 'Investor\OtpController@index');
    $router->post('password/reset', 'Auth\InvestorController@resetpassword');
    $router->get('privacy-policy', 'Administrative\Service\ServiceController@index');
    $router->post('questions', 'LandingsController@questions');
    $router->get('revenue', 'TransactionsController@revenue');
    $router->get('revenue-date', 'TransactionsController@revenue_date');
    $router->get('running-text', 'SA\UI\MarqueeController@running_text');
    $router->get('socialmedia', 'SA\UI\SocialMediaController@index');
    $router->get('terms-conditions', 'Administrative\Service\ServiceController@index');
});