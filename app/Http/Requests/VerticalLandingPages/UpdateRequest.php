<?php

namespace App\Http\Requests\VerticalLandingPages;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:150',
            // ignore current record's url on unique check
            'url'         => 'required|string|max:255|unique:vertical_landing_pages,url,' . $this->route('vertical_landing_page'),
            'is_external' => 'boolean',
            'vertical_id' => 'required|exists:verticals,id',
            'company_id'  => 'nullable|required_if:is_external,true|exists:companies,id',
            'active'      => 'boolean',
        ];
    }
}