<?php

namespace App\Providers;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Domain\Accounting\Services\AccountingService;
use App\Domain\Commercial\Contracts\QuoteServiceInterface;
use App\Domain\Commercial\Services\QuoteService;
use App\Domain\Planning\Contracts\BookingServiceInterface;
use App\Domain\Planning\Contracts\SpaBookingRepositoryInterface;
use App\Domain\Planning\Repositories\SpaBookingRepository;
use App\Domain\Planning\Services\BookingService;
use App\Domain\Resources\Contracts\ResourceAllocationServiceInterface;
use App\Domain\Resources\Services\ResourceAllocationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SpaBookingRepositoryInterface::class, SpaBookingRepository::class);
        $this->app->bind(BookingServiceInterface::class, BookingService::class);
        $this->app->bind(ResourceAllocationServiceInterface::class, ResourceAllocationService::class);
        $this->app->bind(QuoteServiceInterface::class, QuoteService::class);
        $this->app->bind(AccountingServiceInterface::class, AccountingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
