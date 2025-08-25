<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchPayoutRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   */
  public function authorize(): bool {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      'clid' => 'required|string|max:255',
      'fromDate' => 'required|date|date_format:Y-m-d|before_or_equal:toDate',
      'toDate' => 'required|date|date_format:Y-m-d|after_or_equal:fromDate|before_or_equal:today'
    ];
  }

  /**
   * Get custom messages for validator errors.
   *
   * @return array<string, string>
   */
  public function messages(): array
  {
    return [
      'clid.required' => 'The clid field is required.',
      'clid.string' => 'The clid field must be a string.',
      'clid.max' => 'The clid field cannot exceed 255 characters.',
      'fromDate.required' => 'The from date field is required.',
      'fromDate.date' => 'The from date must be a valid date.',
      'fromDate.date_format' => 'The from date must have the format Y-m-d.',
      'fromDate.before_or_equal' => 'The from date must be before or equal to the to date.',
      'toDate.required' => 'The to date field is required.',
      'toDate.date' => 'The to date must be a valid date.',
      'toDate.date_format' => 'The to date must have the format Y-m-d.',
      'toDate.after_or_equal' => 'The to date must be after or equal to the from date.',
      'toDate.before_or_equal' => 'The to date cannot be after today.'
    ];
  }
}
