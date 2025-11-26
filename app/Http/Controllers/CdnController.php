<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class CdnController extends Controller
{
  public function loader(Request $request)
  {
    $version = $request->query('version', '1.0'); // v1.0 por defecto
    $availableVersions = ['1.0', '1.1'];

    if (!in_array($version, $availableVersions)) {
      return response('Invalid version specified.', 400);
    }

    $baseUrl = $this->getCdnAsset("v{$version}");

    // Pasa los parámetros originales (excepto 'version') al script de la versión
    $queryParams = http_build_query($request->except('version'));
    $finalUrl = $baseUrl . '?' . $queryParams;

    $content = view('catalyst.loader', ['finalUrl' => $finalUrl])->render();

    return response($content)->header('Content-Type', 'application/javascript');
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
    $manifest = $this->getCdnManifest();
    $key = "resources/js/catalyst/{$entry}.js";
    if (!isset($manifest[$key])) {
      abort(404, "CDN asset not found in manifest: {$key}");
    }
    return asset('cdn/catalyst/' . $manifest[$key]['file']);
  }
}
