<?php

/*** BEGIN Investor ***/
$router->post('email/request-verification', ['as' => 'email.request.verification', 'uses' => 'Auth\InvestorController@emailRequestVerification']);
$router->get('financial-ratio/published', 'SA\Master\FinancialCheckUp\RatioController@published');
$router->get('menu/investor', 'SA\UI\Menus\MenusController@user');
$router->get('product/benchmark', 'SA\Assets\Products\ProductsController@benchmark');
$router->get('user', 'Users\InvestorController@index');
$router->get('user/detail/{id}', 'Users\InvestorController@detail_edit');
$router->get('users/auth/investor', ['as' => 'users.auth.investor', 'uses' => 'Auth\InvestorController@user_auth']);
//$router->get('users/investors', ['as' => 'users.auth.investor', 'uses' => 'Auth\InvestorController@user_auth']);
$router->get('users/investor', ['as' => 'users.auth.investor', 'uses' => 'Auth\InvestorController@user_auth']);
$router->post('questions', 'LandingsController@questions');

$router->group(['prefix' => 'auth/investor'], function () use ($router) {
    $router->get('check-reset', 'Auth\InvestorController@check_reset');
    $router->put('change-password', 'Auth\InvestorController@change_password');
    $router->post('logout', 'Auth\InvestorController@logout');
    $router->put('password-reset', 'Auth\InvestorController@password_reset');
    $router->post('password-verify', 'Auth\InvestorController@password_verify');
    $router->put('valid-account', 'Auth\InvestorController@valid_account');
});

$router->group(['prefix' => 'calculator'], function () use ($router) {
    $router->group(['prefix' => 'product-investment'], function () use ($router) {
        $router->post('calculate', 'Calculator\ProductsInvestmentController@calculate');
    });
}); 

/*** BEGIN Financial ***/
/*** BEGIN Condition ***/
$router->group(['prefix' => 'assets-liabilities'], function() use ($router) {
    $router->get('list', 'SA\Master\FinancialCheckUp\FinancialController@index');
    $router->post('total', 'Financial\Condition\AssetsLiabilitiesController@total');
    $router->post('total-name', 'Financial\Condition\AssetsLiabilitiesController@totalByName');
});
__router($router, 'Financial\Condition\AssetsLiabilities', 'assets-liabilities');
$router->post('assets-liquidity/total', 'Financial\Condition\AssetsLiabilitiesController@total');

$router->post('income-expense/total', 'Financial\Condition\IncomeExpenseController@total');
$router->post('income-expense/total-name', 'Financial\Condition\IncomeExpenseController@totalByName');
$router->get('income-expense/list', 'SA\Master\FinancialCheckUp\FinancialController@index');
__router($router, 'Financial\Condition\IncomeExpense', 'income-expense');
/*** END Condition ***/

/*** BEGIN Planning & Construction ***/
$router->get('current-portfolio', 'Financial\Planning\CurrentPortfolioController@index');
$router->get('investor-current-portfolio', 'Financial\Planning\CurrentPortfolioController@current_portfolio');
$router->get('investor-current-portfolio-total', 'Financial\Planning\CurrentPortfolioController@current_assets_total');
$router->group(['prefix' => 'goals-setup'], function() use ($router) {
    $router->post('backtest', 'Financial\Planning\GoalsSetupController@backtest');
    $router->post('calculator', 'Financial\Planning\GoalsSetupController@calculator');
    $router->get('change-product', 'Financial\Planning\GoalsSetupController@change_product');
    $router->get('check-bank', 'Financial\Planning\GoalsSetupController@check_bank_account');
    $router->get('detail/{id}', 'Financial\Planning\GoalsSetupController@detail');
    $router->get('fee-product/{id}/{type}', 'Financial\Planning\GoalsSetupController@fee_product');
    $router->get('goal-detail/{id}', 'SA\Reference\GoalsController@detail');
    $router->get('model', 'Financial\Planning\GoalsSetupController@get_model');
    $router->get('model-detail/{id}', 'SA\Assets\Portfolio\ModelsController@detail');
    $router->get('risk-profile-detail/{id}', 'SA\Reference\KYC\RiskProfiles\ProfilesController@detail');
    $router->post('save-checkout/save', 'Financial\Planning\GoalsSetupController@save_checkout');
    //$router->post('save-checkout/save', 'Financial\Planning\GoalsSetupController@saveNonGoals');
    $router->put('save-checkout/save/{id}', 'Financial\Planning\GoalsSetupController@save_checkout');
    $router->post('save-summary/save', 'Financial\Planning\GoalsSetupController@save_checkout');
    $router->get('timestamp', 'Financial\Planning\GoalsSetupController@timestamp');
    $router->get('tools', 'Financial\Planning\GoalsSetupController@tools');
    $router->get('/', 'Financial\Planning\GoalsSetupController@index');
});
$router->group(['prefix' => 'retirement-planning'], function() use ($router) {
    $router->post('backtest', 'Financial\Planning\RetirementController@backtest');
    $router->post('calculator', 'Financial\Planning\RetirementController@calculator');
    $router->post('checkout/save', 'Financial\Planning\RetirementController@save_checkout');
});
/*** END Planning & Construction ***/
/*** END Financial ***/

$router->group(['prefix' => 'investor'], function() use ($router) {
    $router->get('address', 'Investor\InvestorsController@address');
    $router->get('asset-class', 'SA\Assets\AssetClassController@index');
    $router->post('change-address', 'Investor\InvestorsController@change_address');
    $router->put('change-address', 'Investor\InvestorsController@change_address');
    $router->put('change-photo', 'Investor\InvestorsController@change_photo');
    $router->get('bank-account', 'Investor\InvestorsController@bank_account');
    $router->get('investor-card', 'Investor\InvestorsController@investor_card');
    $router->get('risk-profile', 'Investor\InvestorsController@risk_profile');
    $router->get('sales', 'Investor\InvestorsController@sales');
    $router->get('sync-bank-account', 'Auth\InvestorController@sync_bankaccount_data');
    $router->get('validation-before-purchase', 'Users\InvestorController@validation_before_purchase');

    /*** BEGIN Terms and Condition ***/
    $router->group(['prefix' => 'terms-condition'], function() use ($router) {
        $router->get('code/{code}', 'Administrative\Service\TermsController@terms_code');
        $router->get('/', 'Administrative\Service\TermsController@index');
    });
    /*** END Terms and Condition ***/

    /*** Portfolio Model ***/
    $router->group(['prefix' => 'portfolio-model'], function() use ($router) {
        $router->get('detail/{id}', 'Portfolio\PortfolioModelController@detail');
        $router->get('product', 'Portfolio\PortfolioModelController@model_product');
        $router->get('/', 'Portfolio\PortfolioModelController@index');
    });
});

$router->group(['prefix' => 'kyc'], function() use ($router) {
    $router->post('earnings', 'SA\Reference\KYC\EarningsController@index');
    $router->post('fund-source', 'SA\Reference\KYC\FundSourcesController@index');
    $router->post('gender', 'SA\Reference\KYC\GenderController@index');
    $router->post('investment-objective', 'SA\Reference\KYC\InvestmentObjectiveController@index');
    $router->get('questionnaire', 'SA\Reference\KYC\RiskProfiles\QuestionController@questionnaire');
    $router->post('risk-profile', 'Investor\KycController@risk_profile');
    // save Registration
    $router->put('save/{id}', 'Investor\KycController@save');
    $router->post('set-domicile/{investor_id}', 'Investor\KycRegistrationController@saveDomicileAddress');
    $router->get('investor-initial-data/{id}', 'Investor\KycRegistrationController@getInvestorData');
    $router->post('set-address', 'Investor\KycRegistrationController@saveInvestorAddress');
    $router->get('get-address', 'Investor\KycRegistrationController@getAddressList');
    $router->post('request-sid', 'Investor\KycRegistrationController@makeRequestSid');
    $router->post('check-riskprofile', 'Investor\KycRegistrationController@check_risk_profile');
    $router->get('sync-investor', 'Investor\KycRegistrationController@sync_investor');

    $router->get('investor-address-data/{id}', 'Investor\KycRegistrationController@getInvestorAddressData');
    $router->post('wms/auth', 'Investor\KycRegistrationController@authenticateToWms');
    $router->get('sid', 'Investor\KycRegistrationController@getSid');
    $router->get('termscondition', 'Investor\KycRegistrationController@termscondition');
    $router->post('save/{id}', 'Investor\KycRegistrationController@savedata1');
    $router->post('/', 'Investor\KycController@detail_profile');

    // fetch Investor Data for Registration
    $router->get('investors/{id}', 'Investor\InvestorsData@investorsdata');
    $router->get('bank_accounts/{id}', 'Investor\InvestorsData@bankaccounts');
    $router->get('questiondata/{id}', 'Investor\InvestorsData@questionaire');

    $router->get('optionform/{tablename}', 'Investor\InvestorsData@optionform');
});

# routing mendapatakan data portfolio terkait
$router->group(['prefix' => 'rebalance'], function () use ($router) {
    $router->get('portfolio-data', 'Investor\Rebalance\InvestorRebalanceController@index'); # coba di hit ke sini untuk daftar tampilan portfolio rebalancing yang akan di checkout
});

# post api external untuk mendapatkan data FEE pada saat checkout
$router->group(['prefix' => 'inquiry-tax'], function () use ($router) {
    $router->get('get-cif-number', 'Investor\InquiryFeeTaxController@getCifNumber'); # this route is for develeopment purpose, if app is production, it mustbe deactivated.
    $router->get('generate-request-body', 'Investor\InquiryFeeTaxController@generateRequestBody'); # this route is for develeopment purpose, if app is production, it mustbe deactivated.
    $router->post('send-request', 'Investor\InquiryFeeTaxController@sendRequest'); # send request ke sini dr cpm.
    $router->get('feetaxadapter', 'Investor\InquiryFeeTaxController@feetaxadapter');
});

$router->group(['prefix' => 'reference'], function () use ($router) {
    foreach (['province', 'city', 'sub-district', 'urban-village'] as $rg) {
        $router->get($rg . '/region', 'SA\Reference\KYC\RegionsController@index');
    }
});

$router->group(['prefix' => 'transaction'], function() use ($router) {
    $router->get('account-balance', 'TransactionsController@account_balance');
    $router->get('account-balance-redeem', 'TransactionsController@account_balance_redeem');
    $router->get('goals-balance', 'TransactionsController@goals_balance');
    $router->get('redemption', 'TransactionsController@redemption');
    $router->post('redeem-all/checkout/save', 'TransactionsController@save_redeem');
    $router->post('topup/checkout/save', 'TransactionsController@save_topup');
    $router->get('transaction_redeem/{id}', 'TransactionsController@transaction_redeem');
    $router->put('update-account', 'TransactionsController@update_account');
    $router->get('cut-of-time/{product_id}', 'TransactionsController@cut_of_time');
    $router->get('product-switching/{product_id}', 'TransactionsController@get_product_switching');
    $router->get('otp-send', 'TransactionsController@otp_send');
    $router->get('otp-verified/{otp}', 'TransactionsController@otp_verified');
    $router->post('redeem-all/checkout/save', 'TransactionsController@save_redeem');
    $router->post('current/redeem/checkout/save', 'TransactionsController@save_redeem');
    $router->get('product-switching', 'TransactionsController@product_switching');
    $router->get('product/{id}', 'TransactionsController@product_buy');
        $router->get('product_detail', 'TransactionsController@product_detail');
    $router->post('switching/checkout/save', 'TransactionsController@save_switching');
});

$router->group(['prefix' => 'watchlist'], function() use ($router) {
    $router->get('product', 'WatchlistController@product');
    $router->get('goals', 'WatchlistController@goals');
    $router->get('/', 'WatchlistController@saveWatchlist');
});

/*** END Administrative Tools ***/

/*** BEGIN Service ***/
//$router->group(['prefix' => 'service'], function() use ($router) {
    /*** BEGIN Glossary ***/
    //$router->get('glossary', 'Administrative\Service\GlossariesController@index');
    // __router($router, 'Administrative\Service\Glossaries', 'glossary');
//});

//SaveCheckoutGoalPerformance
//$router->post('goals-setup/save-checkout/save', 'Financial\Planning\GoalsSetupController@SaveCheckoutGoalPerformance');


/*** END Investor ***/
