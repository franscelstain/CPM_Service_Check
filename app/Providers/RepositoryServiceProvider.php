<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Interfaces\NotificationRepositoryInterface;
use App\Interfaces\Auth\InvestorAuthRepositoryInterface;
use App\Interfaces\Auth\UserAuthRepositoryInterface;
use App\Interfaces\Finance\AssetOutstandingRepositoryInterface;
use App\Interfaces\Finance\FinancialRepositoryInterface;
use App\Interfaces\Finance\LiabilitiesOutstandingRepositoryInterface;
use App\Interfaces\Finance\TransHistoryRepositoryInterface;
use App\Interfaces\Products\CouponRepositoryInterface;
use App\Interfaces\Products\PriceRepositoryInterface;
use App\Interfaces\Products\ProductRepositoryInterface;
use App\Interfaces\Users\AumRepositoryInterface;
use App\Interfaces\Users\InvestorRepositoryInterface;

use App\Repositories\NotificationRepository;
use App\Repositories\Auth\InvestorAuthRepository;
use App\Repositories\Auth\UserAuthRepository;
use App\Repositories\Finance\AssetOutstandingRepository;
use App\Repositories\Finance\FinancialRepository;
use App\Repositories\Finance\LiabilitiesOutstandingRepository;
use App\Repositories\Finance\TransHistoryRepository;
use App\Repositories\Products\CouponRepository;
use App\Repositories\Products\PriceRepository;
use App\Repositories\Products\ProductRepository;
use App\Repositories\Users\AumRepository;
use App\Repositories\Users\InvestorRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(AssetOutstandingRepositoryInterface::class, AssetOutstandingRepository::class);
        $this->app->bind(AumRepositoryInterface::class, AumRepository::class);
        $this->app->bind(CouponRepositoryInterface::class, CouponRepository::class);
        $this->app->bind(FinancialRepositoryInterface::class, FinancialRepository::class);
        $this->app->bind(InvestorRepositoryInterface::class, InvestorRepository::class);
        $this->app->bind(InvestorAuthRepositoryInterface::class, InvestorAuthRepository::class);
        $this->app->bind(LiabilitiesOutstandingRepositoryInterface::class, LiabilitiesOutstandingRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(PriceRepositoryInterface::class, PriceRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(TransHistoryRepositoryInterface::class, TransHistoryRepository::class);
        $this->app->bind(UserAuthRepositoryInterface::class, UserAuthRepository::class);
    }
}
