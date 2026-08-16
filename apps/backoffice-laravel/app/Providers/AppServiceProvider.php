<?php

namespace App\Providers;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Domain\Accounting\Contracts\CashSessionExpectedAmountServiceInterface;
use App\Domain\Accounting\Services\AccountingService;
use App\Domain\Accounting\Services\CashSessionExpectedAmountService;
use App\Domain\Catalog\Contracts\ServiceCatalogRepositoryInterface;
use App\Domain\Catalog\Contracts\ServiceCatalogServiceInterface;
use App\Domain\Catalog\Repositories\ServiceCatalogRepository;
use App\Domain\Catalog\Services\ServiceCatalogService;
use App\Domain\Clinical\Contracts\ClinicalDiagnosisServiceInterface;
use App\Domain\Clinical\Contracts\ClinicalVisitServiceInterface;
use App\Domain\Clinical\Services\ClinicalDiagnosisService;
use App\Domain\Clinical\Services\ClinicalVisitService;
use App\Domain\Commercial\Contracts\QuoteServiceInterface;
use App\Domain\Commercial\Services\QuoteService;
use App\Domain\GoogleCalendar\Contracts\GoogleCalendarSyncServiceInterface;
use App\Domain\GoogleCalendar\Services\GoogleCalendarSyncService;
use App\Domain\Inventory\Contracts\BookingStockConsumptionServiceInterface;
use App\Domain\Inventory\Contracts\ItemMovementServiceInterface;
use App\Domain\Inventory\Services\BookingStockConsumptionService;
use App\Domain\Inventory\Services\ItemMovementService;
use App\Domain\MetaCatalog\Contracts\MetaCatalogSyncServiceInterface;
use App\Domain\MetaCatalog\Services\MetaCatalogSyncService;
use App\Domain\Planning\Contracts\BookingServiceInterface;
use App\Domain\Planning\Contracts\SpaBookingRepositoryInterface;
use App\Domain\Planning\Repositories\SpaBookingRepository;
use App\Domain\Planning\Services\BookingService;
use App\Domain\Resources\Contracts\ResourceAllocationServiceInterface;
use App\Domain\Resources\Services\ResourceAllocationService;
use App\Domain\WhatsAppMessaging\Contracts\WhatsAppSenderInterface;
use App\Domain\WhatsAppMessaging\Services\MetaWhatsAppSender;
use App\Support\SystemSettings\SystemSettings;
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
        $this->app->bind(CashSessionExpectedAmountServiceInterface::class, CashSessionExpectedAmountService::class);
        $this->app->bind(ServiceCatalogRepositoryInterface::class, ServiceCatalogRepository::class);
        $this->app->bind(ServiceCatalogServiceInterface::class, ServiceCatalogService::class);
        $this->app->bind(ClinicalVisitServiceInterface::class, ClinicalVisitService::class);
        $this->app->bind(ClinicalDiagnosisServiceInterface::class, ClinicalDiagnosisService::class);
        $this->app->bind(ItemMovementServiceInterface::class, ItemMovementService::class);
        $this->app->bind(BookingStockConsumptionServiceInterface::class, BookingStockConsumptionService::class);
        $this->app->bind(MetaCatalogSyncServiceInterface::class, MetaCatalogSyncService::class);
        $this->app->bind(WhatsAppSenderInterface::class, MetaWhatsAppSender::class);
        $this->app->bind(GoogleCalendarSyncServiceInterface::class, GoogleCalendarSyncService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fija la zona horaria real ANTES de que corra cualquier otra cosa (rutas,
        // middleware, comandos artisan, jobs en cola) — a diferencia de aplicarlo
        // solo en ApplySystemSettings (middleware, solo corre en requests HTTP),
        // esto cubre también artisan/queue/tests, y evita que un now() calculado
        // antes del middleware quede en una zona horaria distinta del now() que
        // ve el controlador después. date_default_timezone_set() es un estado
        // global de PHP — fijarlo aquí, una sola vez al boot, es lo que lo hace
        // consistente en todo el proceso.
        try {
            $timezone = (string) (app(SystemSettings::class)->all()['system_timezone'] ?? '');
            if ($timezone !== '') {
                date_default_timezone_set($timezone);
                config(['app.timezone' => $timezone]);
            }
        } catch (\Throwable) {
            // Tabla de configuración aún no existe (ej. antes de la primera migración) — se queda con el default de config/app.php.
        }
    }
}
