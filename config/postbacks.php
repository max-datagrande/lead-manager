<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Postback Retry Interval
  |--------------------------------------------------------------------------
  |
  | Intervalo en minutos para reprocesar ejecuciones de postback fallidas
  | que son elegibles para reintento (status FAILED, attempts < max,
  | next_retry_at <= now).
  |
  */

  'retry_interval' => (int) env('POSTBACK_RETRY_INTERVAL', 30),

];
