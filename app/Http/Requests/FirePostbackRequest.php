<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request para validar datos del postback a Natural Intelligence
 */
class FirePostbackRequest extends FormRequest
{
  /**
   * Determina si el usuario está autorizado para hacer esta request
   */
  public function authorize(): bool
  {
    return true; // Autorización manejada por middleware si es necesario
  }

  /**
   * Reglas de validación para el postback
   */
  public function rules(): array
  {
    //?clid={pub_param_1}&payout={pub_param_2}&txid={OPTIONAL}&currency=USD&event={OPTIONAL}&offer_id={required}
    return [
      'clid' => 'nullable|string|max:255',
      'payout' => 'nullable|numeric|min:0|max:999999.99',
      'txid' => 'nullable|string|max:255',
      'currency' => 'nullable|string|size:3|in:USD,EUR,GBP,CAD,AUD,JPY,CHF,SEK,NOK,DKK',
      'event' => 'nullable|string|max:100',
      'offer_id' => 'required|string|max:255',
      'vendor' => 'required|string|max:63'
    ];
  }
}
