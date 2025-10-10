<?php

namespace App\Http\Controllers\Api\Offerwall;

use App\Http\Controllers\Controller;
use App\Models\OfferwallMix;
use App\Services\Offerwall\MixService;
use Illuminate\Http\Request;

class MixController extends Controller
{
  protected $mixService;

  public function __construct(MixService $mixService)
  {
    $this->mixService = $mixService;
  }

  public function trigger(Request $request, OfferwallMix $offerwallMix)
  {
    $validated = $request->validate([
      'fingerprint' => 'required|string',
    ]);
    $offers = $this->mixService->fetchAndAggregateOffers($offerwallMix, $validated['fingerprint']);

    return response()->json($offers);
  }
}
