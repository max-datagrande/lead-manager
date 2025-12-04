<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use MatthiasMullie\Minify;


class CatalystController extends Controller
{
  public function loader(Request $request)
  {
    $version = $request->query('version', '1.0'); // v1.0 por defecto
    $availableVersions = ['1.0', '1.1'];
    if (!in_array($version, $availableVersions)) {
      return response('Invalid version specified.', 400);
    }

    $baseUrl = $this->getCdnAsset("v{$version}");

    $isDebug = $request->query('catalyst_debug') === '1';
    $sessionData = $isDebug ? $request->session()->all() : null;

    $catalystConfig = [
      'debug' => $isDebug,
      'session' => $sessionData,
      'environment' => config('app.env', 'production'),
      'api_url' => config('catalyst.api_url'),
      'active' => config('catalyst.active', true),
    ];

    // Pasa los parámetros originales (excepto 'version' y 'catalyst_debug') al script de la versión
    $queryParams = http_build_query($request->except('version', 'catalyst_debug'));
    $finalUrl = $baseUrl . ($queryParams ? '?' . $queryParams : '');

    $content = view('catalyst.loader', [
      'finalUrl' => $finalUrl,
      'catalystConfig' => $catalystConfig,
    ])->render();

      $minifier = new Minify\JS($content);
      $content = $minifier->minify();

    return response($content)->header('Content-Type', 'application/javascript');
  }

  /**
   * Sirve la versión compilada del SDK solicitada.
   * Útil para cargar el script directamente desde landings externas evitando problemas de CORS.
   */
  public function asset(Request $request, string $version)
  {
    // Validación simple de formato de versión (v1.0, v1.1, etc)
    // Permitimos pasar solo '1.0' o 'v1.0'
    $versionKey = str_starts_with($version, 'v') ? $version : "v{$version}";

    $availableVersions = ['v1.0', 'v1.1'];
    if (!in_array($versionKey, $availableVersions)) {
      return response('Invalid version specified.', 400);
    }

    // En entorno local con hot reload, redirigimos al servidor de Vite
    if (app()->isLocal()) {
      $hotFilePath = public_path('catalyst.hot');
      if (File::exists($hotFilePath)) {
        $assetUrl = $this->getCdnAsset($versionKey);
        return redirect($assetUrl);
      }
    }

    // En producción o sin hot reload, servimos el archivo directamente
    $manifest = $this->getCdnManifest();

    $keyTs = "resources/js/catalyst/{$versionKey}.ts";
    $keyJs = "resources/js/catalyst/{$versionKey}.js";
    $fileInfo = null;

    if (isset($manifest[$keyTs])) {
        $fileInfo = $manifest[$keyTs];
    } elseif (isset($manifest[$keyJs])) {
        $fileInfo = $manifest[$keyJs];
    }

    if (!$fileInfo) {
      abort(404, "CDN asset not found in manifest: {$versionKey}");
    }

    $path = public_path('cdn/catalyst/' . $fileInfo['file']);
    if (!File::exists($path)) {
        abort(404, 'File not found');
    }

    return response()->file($path, [
        'Content-Type' => 'application/javascript',
        'Access-Control-Allow-Origin' => '*', // Forzar header CORS explícitamente
    ]);
  }

  private function getCdnManifest()
  {
    return Cache::rememberForever('catalyst.manifest', function () {
      $manifestPath = public_path('cdn/catalyst/manifest.json');
      if (!File::exists($manifestPath)) {
        return [];
      }
      return json_decode(File::get($manifestPath), true);
    });
  }

  private function getCdnAsset(string $entry)
  {
    if (app()->isLocal()) {
      $hotFilePath = public_path('catalyst.hot');
      if (File::exists($hotFilePath)) {
        $hotFileContent = File::get($hotFilePath);
        $baseUrl = trim($hotFileContent);
        $entryFile = "resources/js/catalyst/{$entry}.ts"; // Asumimos TS en dev por defecto, pero podría fallar si es JS
        // Mejor verificar si existe el archivo fuente o simplemente apuntar al TS ya que es lo que usamos
        return "{$baseUrl}/{$entryFile}";
      }
    }

    $manifest = $this->getCdnManifest();
    $keyTs = "resources/js/catalyst/{$entry}.ts";
    $keyJs = "resources/js/catalyst/{$entry}.js";

    if (isset($manifest[$keyTs])) {
        return asset('cdn/catalyst/' . $manifest[$keyTs]['file']);
    }
    if (isset($manifest[$keyJs])) {
        return asset('cdn/catalyst/' . $manifest[$keyJs]['file']);
    }

    abort(404, "CDN asset not found in manifest: {$entry}");
  }
}
