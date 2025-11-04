<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MaxconvService;
use App\Models\Postback;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MaxconvController extends Controller
{
    public function __construct(
        protected MaxconvService $maxconvService
    ) {}

    /**
     * Obtiene todas las ofertas disponibles
     */
    public function getOffers(): JsonResponse
    {
        $offers = $this->maxconvService->getAllOffers();
        
        return response()->json([
            'success' => true,
            'data' => $offers
        ]);
    }

    /**
     * Obtiene una oferta específica
     */
    public function getOffer(string $offerId): JsonResponse
    {
        $offer = $this->maxconvService->getOffer($offerId);
        
        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'Oferta no encontrada'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $offer
        ]);
    }

    /**
     * Construye una URL de oferta con placeholders procesados
     */
    public function buildOfferUrl(Request $request): JsonResponse
    {
        $request->validate([
            'offer_id' => 'required|string',
            'data' => 'required|array'
        ]);

        $url = $this->maxconvService->buildOfferUrl(
            $request->input('offer_id'),
            $request->input('data')
        );

        if (!$url) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo construir la URL de la oferta'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => ['url' => $url]
        ]);
    }

    /**
     * Valida placeholders para una URL específica
     */
    public function validatePlaceholders(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|string',
            'data' => 'required|array'
        ]);

        $missingPlaceholders = $this->maxconvService->validatePlaceholders(
            $request->input('url'),
            $request->input('data')
        );

        return response()->json([
            'success' => true,
            'data' => [
                'is_valid' => empty($missingPlaceholders),
                'missing_placeholders' => $missingPlaceholders
            ]
        ]);
    }

    /**
     * Preview de datos de postback para un postback específico
     */
    public function previewPostbackData(int $postbackId): JsonResponse
    {
        $postback = Postback::find($postbackId);
        
        if (!$postback) {
            return response()->json([
                'success' => false,
                'message' => 'Postback no encontrado'
            ], 404);
        }

        $postbackData = $this->maxconvService->buildPostbackData($postback);
        $postbackUrl = $this->maxconvService->buildPostbackUrl($postback);

        return response()->json([
            'success' => true,
            'data' => [
                'postback_data' => $postbackData,
                'postback_url' => $postbackUrl,
                'offer_id' => $postback->offer_id
            ]
        ]);
    }
}