<?php

/*** BEGIN Administrative Tools ***/
/** BEGIN API***/
$router->group(['prefix' => 'api'], function () use ($router) {
    __router($router, 'Administrative\Api\ErrorCode', 'errors');
    __router($router, 'Administrative\Api\Host', 'host');
    __router($router, 'Administrative\Api\HttpCode', 'httpcode');
    __router($router, 'Administrative\Api\Services', 'service');
    $router->post('host/list', 'Administrative\Api\HostController@lists');
    $router->get('service/param', 'Administrative\Api\ServicesController@param');

});
/*** END API **/

/* set up notif */
$router->group(['prefix' => 'notification-setup'], function($router) {
    $router->get('detail/{id}', 'Administrative\NotificationsController@detail');
    $router->get('interval/{id}', 'Administrative\NotificationsController@interval');
    $router->delete('save/{id}', 'Administrative\NotificationsController@delete');
    $router->put('save/{id}', 'Administrative\NotificationsController@save');
    $router->post('save', 'Administrative\NotificationsController@save');
    $router->get('/', 'Administrative\NotificationsController@index');
});

$router->group(['prefix' => 'notif-register'], function () use ($router) {
    $router->get('notif-users/get-data', 'Administrative\Notify\UsersNotificationController@get_data');
    // $router->post('notif-users/save', 'Administrative\Notify\NotificationController@notificationReceivedAll');
});

$router->group(['prefix' => 'auth/admin'], function () use ($router) {
    $router->put('change-password', 'Auth\AdminController@change_password');
    $router->post('logout', 'Auth\AdminController@logout');
    $router->put('password-reset', 'Auth\AdminController@password_reset');
    $router->post('password-verify', 'Auth\AdminController@password_verify');
    $router->put('valid-account', 'Auth\AdminController@valid_account');
});

$router->group(['prefix' => 'config'], function () use ($router) {        //taro disini     
    $router->group(['prefix' => 'general'], function () use ($router) {
        $router->post('save', 'Administrative\Config\GeneralController@save');
        $router->get('/', 'Administrative\Config\GeneralController@index');
    });

    $router->group(['prefix' => 'password'], function () use ($router) {
        $router->post('save', 'Administrative\Config\GeneralController@save_password');
        $router->get('/', 'Administrative\Config\GeneralController@password');
    });

    $router->group(['prefix' => 'logo'], function () use ($router) {
        $router->post('save', 'Administrative\Config\LogoController@save');
        $router->post('/', 'Administrative\Config\LogoController@index');
    });

    $router->post('/', 'Administrative\Config\ConfigController@index');
    //$router->get('is-email-exist/{email}', 'Administrative\Config\ConfigController@is_email_exist');
});

$router->group(['prefix' => 'cfg'], function () use ($router) {        //taro disini     
    $router->group(['prefix' => 'general'], function () use ($router) {
        $router->post('save', 'Administrative\Config\GeneralController@save');
        $router->get('/', 'Administrative\Config\GeneralController@index');
    });

    $router->group(['prefix' => 'password'], function () use ($router) {
        $router->post('save', 'Administrative\Config\GeneralController@save_password');
        $router->get('/', 'Administrative\Config\GeneralController@password');
    });

    $router->group(['prefix' => 'logo'], function () use ($router) {
        $router->post('save', 'Administrative\Config\LogoController@save');
        $router->post('/', 'Administrative\Config\LogoController@index');
    });

    $router->post('/', 'Administrative\Config\ConfigController@index');
    //$router->get('is-email-exist/{email}', 'Administrative\Config\ConfigController@is_email_exist');
});
/*** BEGIN Email ***/
$router->group(['prefix' => 'email'], function () use ($router) {
    __router($router, 'Administrative\Email\ContentEmail', 'content');
    __router($router, 'Administrative\Email\Layouts', 'layout');
    $router->post('test/sendmail', 'Administrative\Email\LayoutsController@sendmail');
});
/*** END Email ***/

/*** BEGIN Mobile ***/
$router->group(['prefix' => 'mobile'], function () use ($router) {
    __router($router, 'Administrative\Mobile\ContentSMS', 'content');
});
/*** END Email ***/

/*** BEGIN Service ***/
$router->group(['prefix' => 'service'], function () use ($router) {
    /*** BEGIN Glossary ***/
    __router($router, 'Administrative\Service\Glossaries', 'glossary');
    /*** END Glossary ***/
    __router($router, 'Administrative\Service\Center\Help', 'help-center');
    __router($router, 'Administrative\Service\Center\Categories', 'help-center/category');
    /*** BEGIN Terms an Condition ***/
    __router($router, 'Administrative\Service\Terms', 'terms-conditions/sid');
    /*** END Terms an Condition ***/

    foreach (['terms-conditions', 'privacy-policy'] as $srv) {
        $router->group(['prefix' => $srv], function () use ($router) {
            $router->post('save', 'Administrative\Service\ServiceController@save');
            $router->get('detail/{id}', 'Administrative\Service\ServiceController@detail');
            $router->post('detail/{id}', 'Administrative\Service\ServiceController@detail');
        });
    }
});
/*** END Service ***/
/*** END Administrative Tools ***/

/*** BEGIN Service ***/
$router->group(['prefix' => 'service'], function () use ($router) {
    /*** BEGIN Glossary ***/
    __router($router, 'Administrative\Service\Glossaries', 'glossary');
    /*** END Glossary ***/
    __router($router, 'Administrative\Service\Center\Help', 'help-center');
    __router($router, 'Administrative\Service\Center\Categories', 'help-center/category');

    foreach (['terms-conditions', 'privacy-policy'] as $srv) {
        $router->group(['prefix' => $srv], function () use ($router) {
            $router->post('save', 'Administrative\Service\ServiceController@save');
            $router->get('/', 'Administrative\Service\ServiceController@index');
        });
    }
});
/*** END Service ***/
/*** END Administrative Tools ***/

/*** BEGIN SA ***/
/*** BEGIN Assets ***/
$router->group(['prefix' => 'asset'], function () use ($router) {
    __router($router, 'SA\Assets\AssetCategories', 'category');
    __router($router, 'SA\Assets\AssetClass', 'class');
    __router($router, 'SA\Assets\AssetDocuments', 'document');
    $router->post('class/ws-data', 'SA\Assets\AssetClassController@ws_data');

    /*** BEGIN Portfolio ***/
    $router->group(['prefix' => 'portfolio'], function () use ($router) {
        $router->get('allocation-weights/weight-detail', 'SA\Assets\Portfolio\AllocationWeightsController@detail_weight');
        __router($router, 'SA\Assets\Portfolio\Allocation', 'allocation');
        __router($router, 'SA\Assets\Portfolio\AllocationWeights', 'allocation-weights');
        __router($router, 'SA\Assets\Portfolio\Models', 'model');
        __router($router, 'SA\Assets\Portfolio\ModelMapping', 'model-mapping');
    });
    /*** END Portfolio ***/

    /*** BEGIN Product ***/
    $router->group(['prefix' => 'products'], function () use ($router) {
        __router($router, 'SA\Assets\Products\Fee', 'fee');
        __router($router, 'SA\Assets\Products\FeeReference', 'fee-reference');
        __router($router, 'SA\Assets\Products\Currency', 'currency');
        __router($router, 'SA\Assets\Products\CutOffCurrency', 'currency-cutoff');
        __router($router, 'SA\Assets\Products\Documents', 'document');
        __router($router, 'SA\Assets\Products\Dividen', 'dividen');
        __router($router, 'SA\Assets\Products\Issuer', 'issuer');
        __router($router, 'SA\Assets\Products\Score', 'score');
        __router($router, 'SA\Assets\Products\ThirdParty', 'thirdparty');
        __router($router, 'SA\Assets\Products\ThirdPartyCategory', 'thirdparty-category');
        $router->get('allocation', 'SA\Assets\Products\ProductsController@allocation');
        $router->post('asset', 'SA\Assets\Products\ProductsController@asset');
        $router->get('benchmark', 'SA\Assets\Products\ProductsController@benchmark');
        $router->post('currency/ws-data', 'SA\Assets\Products\CurrencyController@ws_data');
        $router->post('currency-cutoff/ws-data', 'SA\Assets\Products\CutOffCurrencyController@ws_data');
        $router->post('document/category', 'SA\Assets\Products\DocumentsController@category');
        $router->post('document/category_detail', 'SA\Assets\Products\DocumentsController@category_detail');
        $router->post('field', 'SA\Assets\Products\FeeController@field');
        $router->post('issuer/ws-data', 'SA\Assets\Products\IssuerController@ws_data');
        $router->post('thirdparty/ws-data', 'SA\Assets\Products\ThirdPartyController@ws_data');
        $router->post('ws-data', 'SA\Assets\Products\ProductsController@ws_data');
        $router->get('bond', 'SA\Assets\Products\ProductBondController@index'); //add bond here
        $router->get('bond/detail/{id}', 'SA\Assets\Products\ProductBondController@detail'); //add bond edit here
        $router->group(['prefix' => 'coupon'], function () use ($router) {
            $router->post('get-data', 'SA\Assets\Products\CouponController@getData');
            $router->post('import', 'SA\Assets\Products\CouponController@import');
            $router->delete('save/{id}', 'SA\Assets\Products\CouponController@deleteData');
            $router->put('/save/{id}', 'SA\Assets\Products\CouponController@saveData');
            $router->get('{id}', 'SA\Assets\Products\CouponController@detailData');
        });
        $router->group(['prefix' => 'price'], function () use ($router) {        
            $router->get('detail/{id}', 'SA\Assets\Products\PriceController@detail');    
            $router->post('import', 'SA\Assets\Products\PriceController@import');
            $router->post('list', 'SA\Assets\Products\PriceController@listData');
            $router->delete('save/{id}', 'SA\Assets\Products\PriceController@save');
            $router->put('save/{id}', 'SA\Assets\Products\PriceController@save');
            $router->post('save', 'SA\Assets\Products\PriceController@save');
            $router->post('ws-data', 'SA\Assets\Products\PriceController@ws_data');
            $router->get('/', 'SA\Assets\Products\PriceController@index');
        });
    });
    __router($router, 'SA\Assets\Products\Products', 'products');
    /*** END Product ***/
});
/*** END Assets ***/

/*** BEGIN Campaign ***/
$router->group(['prefix' => 'campaign'], function () use ($router) {
    $router->group(['prefix' => 'references'], function () use ($router) {
        foreach (['cart-action', 'cart-item', 'customer-group', 'expired-point', 'investor', 'point-action', 'product'] as $cr) {
            __router($router, 'SA\Campaign\References', $cr);
        }
        $router->get('attribute', 'SA\Campaign\ReferencesController@refAttribute');
    });
    $router->group(['prefix' => 'rewards-rules'], function () use ($router) {
        $router->post('cart/reward', 'SA\Campaign\Rewards\CartController@detail_reward');
        $router->post('point/reward', 'SA\Campaign\Rewards\PointController@detail_reward');
        __router($router, 'SA\Campaign\Rewards\Cart', 'cart');
        __router($router, 'SA\Campaign\Rewards\Point', 'point');
    });
});
/*** END Campaign ***/

/*** BEGIN Master ***/
$router->group(['prefix' => 'master'], function () use ($router) {
    /*** BEGIN Financial Check Up ***/
    foreach (['assets-liabilities', 'income-expense'] as $fcu) {
        __router($router, 'SA\Master\FinancialCheckUp\Financial', $fcu);
        $router->get($fcu . '/seq-to/{type}', 'SA\Master\FinancialCheckUp\FinancialController@seqTo');
    }
    $router->get('assets-liabilities/assetclass/{id}', 'SA\Master\FinancialCheckUp\FinancialController@assetclass');
    __router($router, 'SA\Master\FinancialCheckUp\Ratio', 'financial-ratio');
    $router->post('financial-ratio/published', 'SA\Master\FinancialCheckUp\RatioController@published');
    /*** END Financial Check Up ***/

    /*** BEGIN Macro Economic ***/
    $router->group(['prefix' => 'macro-economic'], function () use ($router) {
        __router($router, 'SA\Master\ME\Categories', 'category');
        __router($router, 'SA\Master\ME\Histinflation', 'history-inflation');
        __router($router, 'SA\Master\ME\Prices', 'price');
        __router($router, 'SA\Master\ME\ExchangeRate', 'exchange-rate');
        $router->post('exchange-rate/import', 'SA\Master\ME\ExchangeRateController@import');
    });
    /*** END Macro Economic ***/

    __router($router, 'SA\Master\News', 'news');

    $router->post('news/content', 'SA\Master\NewsController@content');
});
/*** END Master ***/

/*** BEGIN Reference ***/
$router->group(['prefix' => 'reference'], function () use ($router) {
    /*** BEGIN BANK ***/
    $router->group(['prefix' => 'bank'], function () use ($router) {
        $router->post('account-type/ws-data', 'SA\Reference\Bank\AccountTypesController@ws_data');
        $router->post('branch/ws-data', 'SA\Reference\Bank\BranchController@ws_data');
        __router($router, 'SA\Reference\Bank\AccountTypes', 'account-type');
        __router($router, 'SA\Reference\Bank\Branch', 'branch');
    });
    __router($router, 'SA\Reference\Bank\Bank', 'bank');
    /*** END BANK **/

    /*** BEGIN Goal ***/
    __router($router, 'SA\Reference\Goals', 'goal');
    /*** END Goal ***/

    /*** BEGIN Goal ***/
    $router->post('group/ws-data', 'SA\Reference\GroupsController@ws_data');
    __router($router, 'SA\Reference\Groups', 'group');
    /*** END Goal ***/

    /*** BEGIN KYC ***/
    __router($router, 'SA\Reference\KYC\DocumentType', 'document-type');
    __router($router, 'SA\Reference\KYC\Earnings', 'earnings');
    __router($router, 'SA\Reference\KYC\Education', 'education');
    __router($router, 'SA\Reference\KYC\FundSources', 'fund-source');
    __router($router, 'SA\Reference\KYC\Gender', 'gender');
    __router($router, 'SA\Reference\KYC\Holiday', 'holiday');
    __router($router, 'SA\Reference\KYC\InvestmentObjective', 'investment-objective');
    __router($router, 'SA\Reference\KYC\InvestorTypes', 'investor-type');
    __router($router, 'SA\Reference\KYC\MaritalStatus', 'marital');
    __router($router, 'SA\Reference\KYC\Nationality', 'nationality');
    __router($router, 'SA\Reference\KYC\Occupation', 'occupation');
    __router($router, 'SA\Reference\KYC\Religion', 'religion');
    __router($router, 'SA\Reference\KYC\CardType', 'card-type');
    $router->post('document-type/ws-data', 'SA\Reference\KYC\DocumentTypeController@ws_data');
    $router->post('earnings/ws-data', 'SA\Reference\KYC\EarningsController@ws_data');
    $router->post('education/ws-data', 'SA\Reference\KYC\EducationController@ws_data');
    $router->post('fund-source/ws-data', 'SA\Reference\KYC\FundSourcesController@ws_data');
    $router->post('gender/ws-data', 'SA\Reference\KYC\GenderController@ws_data');
    $router->post('holiday/import', 'SA\Reference\KYC\HolidayController@import');
    $router->post('holiday/ws-data', 'SA\Reference\KYC\HolidayController@ws_data');
    $router->post('investment-objective/ws-data', 'SA\Reference\KYC\InvestmentObjectiveController@ws_data');
    $router->post('investor-type/ws-data', 'SA\Reference\KYC\InvestorTypesController@ws_data');
    $router->post('marital/ws-data', 'SA\Reference\KYC\MaritalStatusController@ws_data');
    $router->post('nationality/ws-data', 'SA\Reference\KYC\NationalityController@ws_data');
    $router->post('occupation/ws-data', 'SA\Reference\KYC\OccupationController@ws_data');
    $router->post('province/ws-data', 'SA\Reference\KYC\RegionsController@ws_data');
    $router->post('religion/ws-data', 'SA\Reference\KYC\ReligionController@ws_data');

    foreach (['province', 'city', 'sub-district', 'urban-village'] as $rg) {
        __router($router, 'SA\Reference\KYC\Regions', $rg);
        $router->post($rg . '/import', 'SA\Reference\KYC\RegionsController@import');
    }

    $router->group(['prefix' => 'risk-profile'], function () use ($router) {
        $router->get('answer/question', 'SA\Reference\KYC\RiskProfiles\AnswerController@question');
        $router->post('question/ws-data', 'SA\Reference\KYC\RiskProfiles\QuestionController@ws_data');
        $router->post('ws-data', 'SA\Reference\KYC\RiskProfiles\ProfilesController@ws_data');
        __router($router, 'SA\Reference\KYC\RiskProfiles\Question', 'question');
    });
    __router($router, 'SA\Reference\KYC\RiskProfiles\Profiles', 'risk-profile');
    /*** END KYC ***/
});
/*** END Reference ***/

/*** Begin Transaction ***/
$router->group(['prefix' => 'transaction'], function () use ($router) {
    __router($router, 'SA\Transaction\Reference', 'reference');
});
/** End Transaction ***/

/*** BEGIN UI Elements ***/
/*** BEGIN Icon ***/
$router->get('icon', 'SA\UI\IconsController@index');
/*** END Icon ***/

/*** BEGIN Landing ***/
__router($router, 'SA\UI\Landings\Feature', 'feature');
__router($router, 'SA\UI\Landings\Modules', 'modules');
__router($router, 'SA\UI\Landings\Partner', 'partner');
__router($router, 'SA\UI\Landings\Slide', 'slide');
/*** END Landing ***/

/*** BEGIN Menu ***/
$router->get('button/action', 'SA\UI\ButtonsController@action');
__router($router, 'SA\UI\Buttons', 'button');
__router($router, 'SA\UI\Menus\MenuGroups', 'menu/group');
__router($router, 'SA\UI\Landings\Menus', 'menu/landings');
__router($router, 'SA\UI\Menus\Menus', 'menu');
$router->group(['prefix' => 'menu'], function () use ($router) {
    $router->get('/parent', 'SA\UI\Menus\MenusController@parent');
    $router->get('/sequence_to', 'SA\UI\Menus\MenusController@sequence_to');
    $router->get('/admin', 'SA\UI\Menus\MenusController@user');
});
/*** END Menu ***/

__router($router, 'SA\UI\Marquee', 'marquee');
__router($router, 'SA\UI\SocialMedia', 'socmed');
/*** END UI Elements ***/

/*** BEGIN Users ***/
$router->group(['prefix' => 'users'], function () use ($router) {
    __router($router, 'Users\Categories', 'category');
    __router($router, 'Users\Users', '/');
    $router->get('auth/admin', 'Auth\AdminController@user_auth');
    $router->get('category-users', 'Users\CategoriesController@getcategory');
    $router->put('change-photo', 'Users\UsersController@change_photo');
    
    $router->group(['prefix' => 'investors'], function () use ($router) {
        $router->get('detail/{id}', 'Users\InvestorController@detail');
        $router->get('detail_edit/{id}', 'Users\InvestorController@detail_edit');
        $router->put('save/{id}', 'Users\InvestorController@save');
        $router->get('/', 'Users\InvestorController@index');
    });

    $router->group(['prefix' => 'investor'], function () use ($router) {
        $router->post('get-data', 'Users\InvestorsController@listInvestor');
        $router->get('data/{id}', 'Users\InvestorsController@detailInvestor');
        $router->get('detail/{id}', 'Users\InvestorController@detail');
        $router->get('detail_edit/{id}', 'Users\InvestorController@detail_edit');
        $router->post('list-goals', 'Users\InvestorsController@listWithGoalsForSales');
        $router->put('save/{id}', 'Users\InvestorController@save');
        __router($router, 'Users\Investor\Categories', 'category');
        $router->get('address/{id}', 'Users\InvestorController@address');
        $router->get('bankaccount/{id}', 'Users\InvestorController@bankAccountInvestor');
        $router->get('bank-account/{id}', 'Users\InvestorController@bank_account');
        $router->get('card', 'Users\InvestorController@card');
        $router->get('edd/{id}', 'Users\InvestorController@edd');
        $router->get('risk-profile/{id}', 'Users\InvestorController@risk_profile');
        $router->group(['prefix' => 'priority-card'], function () use ($router) {
            $router->post('import', 'Users\Investor\CardPrioritiesController@import');
            $router->post('list', 'Users\InvestorsController@listPriorityCard');
            $router->get('{id}', 'Users\Investor\CardPrioritiesController@detail');
            $router->get('/', 'Users\Investor\CardPrioritiesController@index');
        });
        $router->get('/', 'Users\InvestorController@index');
    });
    $router->group(['prefix' => 'sales'], function () use ($router) {
        $router->get('branch/{salesid}', 'Users\SalesController@branch');
        $router->post('ws-data', 'Users\SalesController@ws_data');
        $router->get('total', 'Users\InvestorController@totalsales');
        $router->get('detail/sub/{id}', 'Users\SalesController@detail_sub');
        __router($router, 'Users\Sales', '/');
    });
});
/*** END Users ***/
/*** END SA ***/

/*** BEGIN Aum Target ***/
$router->group(['prefix' => 'aum-target'], function () use ($router) {
    __router($router, 'SA\Assets\AumTarget', '/');
});
/*** END Aum Target ***/
/*** END SA ***/

/*** BEGIN investors for admin ***/
$router->group(['prefix' => 'admin/investor'], function () use ($router) {
    $router->get('address', 'Investor\InvestorsController@address');
    $router->get('bank-account', 'Investor\InvestorsController@bank_account');
    $router->get('risk-profile', 'Investor\InvestorsController@risk_profile');
    $router->get('sales', 'Investor\InvestorsController@sales');
});
/*** END investors ***/

$router->group(['prefix' => 'email'], function () use ($router) {
    __router($router, 'SA\Transaction\Reference', 'reference');
});

$router->get('message-get', 'LandingsController@get_message');
$router->get('product-selling', 'TransactionsController@selling_product');
