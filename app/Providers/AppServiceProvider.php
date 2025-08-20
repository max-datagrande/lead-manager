<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    // La mayoría de servicios de TrafficLog son stateless y Laravel los resuelve automáticamente
    // Excepto DeviceDetectionService que es singleton por optimización

    // GeolocationService SÍ necesita ser singleton porque:
    // 1. Mantiene estado (IP del request, geolocalización cacheada)
    // 2. Evita múltiples llamadas a API externa por request
    // 3. La IP es la misma para todo el request
    $this->app->singleton(\App\Services\GeolocationService::class, function ($app) {
      return new \App\Services\GeolocationService(
        $app->make('request'),
        $app->make(\App\Libraries\IpApi::class)
      );
    });

    // DeviceDetectionService como singleton porque:
    // 1. Es costoso de instanciar (crea instancia de Jenssegers\Agent\Agent)
    // 2. Es stateless, puede reutilizarse para múltiples detecciones
    // 3. Optimiza rendimiento evitando múltiples instancias
    $this->app->singleton(\App\Services\TrafficLog\DeviceDetectionService::class);
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    // Request Macro para acceder fácilmente al GeolocationService
    // Uso: $request->geoService()->getIpAdress()
    \Illuminate\Http\Request::macro('geoService', function () {
      return app(\App\Services\GeolocationService::class);
    });
  }
}
