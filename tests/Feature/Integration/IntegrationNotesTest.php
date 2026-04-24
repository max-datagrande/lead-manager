<?php

use App\Models\Company;
use App\Models\Integration;
use App\Models\IntegrationNote;
use App\Services\IntegrationService;

function makeIntegrationNotesPayload(array $overrides = []): array
{
  $company = Company::factory()->create();

  return array_merge(
    [
      'name' => 'Notes Integration',
      'type' => 'post-only',
      'is_active' => true,
      'company_id' => $company->id,
      'field_mappings' => [],
      'environments' => [
        [
          'env_type' => 'post',
          'environment' => 'development',
          'url' => 'https://buyer.example.com/post',
          'method' => 'POST',
          'request_headers' => [],
          'request_body' => null,
          'response_config' => null,
          'field_hashes' => [],
        ],
        [
          'env_type' => 'post',
          'environment' => 'production',
          'url' => 'https://buyer.example.com/post',
          'method' => 'POST',
          'request_headers' => [],
          'request_body' => null,
          'response_config' => null,
          'field_hashes' => [],
        ],
      ],
    ],
    $overrides,
  );
}

// ── createIntegration ─────────────────────────────────────────────────────────

it('does not create a note when payload omits the notes key', function () {
  app(IntegrationService::class)->createIntegration(makeIntegrationNotesPayload());

  expect(IntegrationNote::count())->toBe(0);
});

it('does not create a note when notes is null or empty string', function (mixed $value) {
  app(IntegrationService::class)->createIntegration(makeIntegrationNotesPayload(['notes' => $value]));

  expect(IntegrationNote::count())->toBe(0);
})->with([null, '', '   ']);

it('creates a note when notes has content', function () {
  app(IntegrationService::class)->createIntegration(makeIntegrationNotesPayload(['notes' => "# API Docs\n\n```json\n{\"foo\":1}\n```"]));

  $integration = Integration::latest()->first();

  expect($integration->note)
    ->not->toBeNull()
    ->and($integration->note->content)
    ->toContain('# API Docs')
    ->and($integration->note->content)
    ->toContain('```json');
});

// ── updateIntegration ─────────────────────────────────────────────────────────

it('replaces note content on update and keeps a single row', function () {
  $service = app(IntegrationService::class);
  $service->createIntegration(makeIntegrationNotesPayload(['notes' => 'first version']));

  $integration = Integration::latest()->first();

  $service->updateIntegration(
    $integration,
    makeIntegrationNotesPayload([
      'company_id' => $integration->company_id,
      'notes' => 'second version',
    ]),
  );

  expect(IntegrationNote::where('integration_id', $integration->id)->count())
    ->toBe(1)
    ->and($integration->fresh()->note->content)
    ->toBe('second version');
});

it('deletes existing note when updating with null or empty notes', function (mixed $value) {
  $service = app(IntegrationService::class);
  $service->createIntegration(makeIntegrationNotesPayload(['notes' => 'to be deleted']));

  $integration = Integration::latest()->first();

  $service->updateIntegration(
    $integration,
    makeIntegrationNotesPayload([
      'company_id' => $integration->company_id,
      'notes' => $value,
    ]),
  );

  expect($integration->fresh()->note)->toBeNull();
})->with([null, '', '   ']);

// ── cascade on delete ─────────────────────────────────────────────────────────

it('cascades note deletion when the integration is deleted', function () {
  app(IntegrationService::class)->createIntegration(makeIntegrationNotesPayload(['notes' => 'will die with parent']));

  $integration = Integration::latest()->first();
  $noteId = $integration->note->id;

  $integration->delete();

  expect(IntegrationNote::find($noteId))->toBeNull();
});
