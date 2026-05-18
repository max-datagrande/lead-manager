<?php

namespace App\Http\Requests\LandingPages\Concerns;

use App\Models\Field;
use App\Models\LandingPageColumn;
use App\Services\InternalTokenResolverService;
use Illuminate\Contracts\Validation\Validator;

trait ValidatesColumns
{
  protected function columnRules(): array
  {
    return [
      'columns' => 'array|nullable',
      'columns.*.source' => 'required_with:columns|in:' . LandingPageColumn::SOURCE_FIELD . ',' . LandingPageColumn::SOURCE_TRAFFIC,
      'columns.*.reference' => 'required_with:columns|string',
    ];
  }

  protected function validateColumnReferences(Validator $validator): void
  {
    $columns = $this->input('columns', []);

    if (empty($columns)) {
      return;
    }

    $fieldIds = Field::query()->pluck('id')->map(fn($id) => (string) $id)->all();
    $trafficKeys = collect(app(InternalTokenResolverService::class)->getAvailableTokens()['traffic'])
      ->map(fn($token) => str_replace('traffic.', '', $token['name']))
      ->all();

    foreach ($columns as $i => $col) {
      $source = $col['source'] ?? null;
      $reference = isset($col['reference']) ? (string) $col['reference'] : null;

      if (!$source || $reference === null) {
        continue;
      }

      if ($source === LandingPageColumn::SOURCE_FIELD && !in_array($reference, $fieldIds, true)) {
        $validator->errors()->add("columns.{$i}.reference", 'The referenced field does not exist.');
      }

      if ($source === LandingPageColumn::SOURCE_TRAFFIC && !in_array($reference, $trafficKeys, true)) {
        $validator->errors()->add("columns.{$i}.reference", 'The traffic column key is not allowed.');
      }
    }
  }
}
