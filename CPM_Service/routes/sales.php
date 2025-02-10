<?php

$router->group(['middleware' => 'is_admin'], function () use ($router) {
    $router->get('transaction/sales-fee', 'TransactionsController@sales_fee');
    $router->delete('transaction/save/{id}', 'TransactionsController@save');

    $router->group(['prefix' => 'investor'], function () use ($router) {
        $router->get('financial-score', 'Financial\Condition\AssetsLiabilitiesController@financial_score');
        $router->post('financial-score-data', 'Financial\Condition\AssetsLiabilitiesController@financial_score_data');
        $router->get('max-aum', 'Users\InvestorController@max_aum');
        $router->get('risk-profile-expired', 'Users\InvestorController@risk_profile_expired');
        $router->get('total-investor', 'Users\InvestorsController@totalInvestor');
    });

    $router->group(['prefix' => 'sales'], function () use ($router) {
        $router->group(['prefix' => 'assets-liabilities'], function () use ($router) {
            $router->get('investor', 'Financial\Condition\AssetsLiabilitiesController@investor');
            $router->post('total/{id}', 'Financial\Condition\AssetsLiabilitiesController@total_for_sales');
            $router->post('total-name/{id}', 'Financial\Condition\AssetsLiabilitiesController@total_for_sales_with_name');
            $router->get('/', 'Financial\Condition\AssetsLiabilitiesController@list_for_sales');
        });

        $router->group(['prefix' => 'current'], function () use ($router) {
            $router->get('asset-class', 'SA\Assets\AssetClassController@index');
            $router->get('detail/{id}', 'Sales\Financial\Construction\CurrentController@detail_current');
            $router->get('recomendation', 'SA\Assets\Products\ProductsController@product_recomendation');
            $router->get('/', 'Sales\Financial\Construction\CurrentController@index');
        });

        $router->get('current-portfolio/{id}', 'Financial\Planning\CurrentPortfolioController@detail_for_sales');
        $router->get('investor-current-portfolio', 'Financial\Planning\CurrentPortfolioController@current_portfolio');
        $router->get('investor-current-portfolio-total', 'Financial\Planning\CurrentPortfolioController@current_assets_total');
        $router->group(['prefix' => 'goals-setup'], function () use ($router) {
            $router->post('backtest', 'Financial\Planning\GoalsSetupController@backtest');
            $router->post('calculator', 'Financial\Planning\GoalsSetupController@calculator');
            $router->get('fee-product/{id}', 'Financial\Planning\GoalsSetupController@fee_product');
            $router->get('goal-detail/{id}', 'SA\Reference\GoalsController@detail');
            $router->get('model', 'Financial\Planning\GoalsSetupController@get_model');
            $router->get('model-detail/{id}', 'SA\Assets\Portfolio\ModelsController@detail');
            $router->get('risk-profile-detail/{id}', 'SA\Reference\KYC\RiskProfiles\ProfilesController@detail');
            $router->post('save-summary/save', 'Financial\Planning\GoalsSetupController@save_checkout');
            $router->get('timestamp', 'Financial\Planning\GoalsSetupController@timestamp');
            $router->get('tools', 'Financial\Planning\GoalsSetupController@tools');
            // detail
            $router->get('{investor_id}/{id}', 'Financial\Planning\GoalsSetupController@detail_for_sales');
            //list
            $router->get('{id}', 'Financial\Planning\GoalsSetupController@list_of_goals_with_sales');
        });

        $router->group(['prefix' => 'kyc'], function () use ($router) {
            // fetch Investor Data for Registration
            $router->get('investors/{id}', 'Investor\InvestorsData@investorsdata');
        });

        $router->group(['prefix' => 'income-expense'], function () use ($router) {
            $router->get('investor', 'Financial\Condition\IncomeExpenseController@investor');
            $router->post('total/{id}', 'Financial\Condition\IncomeExpenseController@total_for_sales');
            $router->post('total-name/{id}', 'Financial\Condition\IncomeExpenseController@total_for_sales_with_name');
            $router->get('/', 'Financial\Condition\IncomeExpenseController@list_for_sales');
        });
        $router->post('assets-liquidity/total/{id}', 'Financial\Condition\AssetsLiabilitiesController@total_for_sales');


        $router->group(['prefix' => 'investor'], function () use ($router) {
            $router->get('{id}', 'Users\InvestorController@detail_with_sales');
            $router->get('/', 'Users\InvestorController@list_for_sales');
        });

        $router->group(['prefix' => 'transaction'], function () use ($router) {
            $router->get('account-balance', 'TransactionsController@account_balance');
            $router->get('account-balance-redeem', 'TransactionsController@account_balance_redeem');
            $router->get('goals-balance', 'TransactionsController@goals_balance');
            $router->get('redemption', 'TransactionsController@redemption');
            $router->get('status', 'TransactionsController@status');
            $router->get('transaction_redeem/{id}', 'TransactionsController@transaction_redeem');
            $router->put('update-account', 'TransactionsController@update_account');
        });

        $router->get('bank-account/{investor_id}', 'Investor\InvestorsController@bank_account');
        $router->put('change-password', 'Users\SalesController@change_password');
        $router->put('change-photo', 'Users\SalesController@change_photo');
        $router->get('product/benchmark', 'SA\Assets\Products\ProductsController@benchmark');
        $router->get('product/{id}', 'SA\Assets\Products\ProductsController@product_detail');
        $router->get('report/aum_priority', 'Users\SalesController@aum_priority');
        $router->get('report/drop_fund', 'Users\SalesController@drop_fund');
        $router->get('users/transaction/total/{id}', 'Users\SalesController@transaction_total');
        $router->get('users/transaction/total2/{id}', 'Users\SalesController@transaction_total2');
        $router->get('/', 'Users\SalesController@index');
        $router->get('ws_data', 'Users\SalesController@ws_data');
    });
});
