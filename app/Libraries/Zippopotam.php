<?php

namespace App\Libraries;

use Maxidev\Logger\TailLogger;

class Zippopotam
{
  public function getCityStateByZip($zip)
  {
    $url = "http://api.zippopotam.us/us/{$zip}";
    $response = @file_get_contents($url);
    if ($response === FALSE) {
      $errorMessage = "Error getting Zippopotam data for Zippopotam ZIP code $zip";
      TailLogger::saveLog($errorMessage, "api/zippopotam/");
      return null; // Error en la solicitud
    }
    $data = json_decode($response, true);
    if (!$data || empty($data['places'])) {
      $errorMessage = "No data found for Zippopotam ZIP code $zip";
      TailLogger::saveLog($errorMessage, "api/zippopotam/");
      return null; // ZIP no encontrado
    }
    $firstPlace = $data['places'][0];
    return [
      'city' => $firstPlace['place name'],
      'state' => $firstPlace['state abbreviation']
    ];
  }
}
