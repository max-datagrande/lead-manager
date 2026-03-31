<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
  $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Comandos manuales recomendados (correr desde VPS) ────────────────────────
//
// ping-post:expire-postbacks   → Expira postbacks pendientes vencidos y cierra
//                                dispatches sin venta. Correr diariamente.
//                                php artisan ping-post:expire-postbacks
