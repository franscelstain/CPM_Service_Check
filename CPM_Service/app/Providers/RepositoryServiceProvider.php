<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Interfaces\NotificationRepositoryInterface;
use App\Repositories\NotificationRepository;
use App\Interfaces\Auth\InvestorAuthRepositoryInterface;
use App\Repositories\Auth\InvestorAuthRepository;
use App\Interfaces\Auth\UserAuthRepositoryInterface;
use App\Repositories\Auth\UserAuthRepository;
use App\Interfaces\Balance\AssetOutstandingRepositoryInterface;
use App\Repositories\Balance\AssetOutstandingRepository;
use App\Interfaces\Balance\LiabilitiesOutstandingRepositoryInterface;
use App\Repositories\Balance\LiabilitiesOutstandingRepository;
use App\Interfaces\Products\CouponRepositoryInterface;
use App\Repositories\Products\CouponRepository;
use App\Interfaces\Users\InvestorRepositoryInterface;
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
        $this->app->bind(CouponRepositoryInterface::class, CouponRepository::class);
        $this->app->bind(InvestorRepositoryInterface::class, InvestorRepository::class);
        $this->app->bind(InvestorAuthRepositoryInterface::class, InvestorAuthRepository::class);
        $this->app->bind(LiabilitiesOutstandingRepositoryInterface::class, LiabilitiesOutstandingRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(UserAuthRepositoryInterface::class, UserAuthRepository::class);
    }
}
