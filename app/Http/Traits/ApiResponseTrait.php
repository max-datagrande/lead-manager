<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
  /**
   * Respuesta exitosa
   */
  protected function successResponse($data = null, string $message = 'Success', int $status = 200, $meta = null): JsonResponse
  {
    $body = [
      'success' => true,
      'data' => $data,
      'message' => $message,
    ];
    if ($meta) {
      $body = [...$body, ...$meta];
    }
    return response()->json($body, $status);
  }

  /**
   * Respuesta de error
   */
  protected function errorResponse(string $message = 'Error', $errors = null, int $status = 400): JsonResponse
  {
    $response = [
      'success' => false,
      'data' => null,
      'message' => $message,
    ];

    if ($errors) {
      $response['errors'] = $errors;
    }

    return response()->json($response, $status);
  }

  /**
   * Respuesta de validación
   */
  protected function validationErrorResponse($errors, string $message = 'Validation failed'): JsonResponse
  {
    return $this->errorResponse($message, $errors, 422);
  }
}
