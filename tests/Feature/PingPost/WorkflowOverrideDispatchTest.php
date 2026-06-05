<?php

use App\Events\LeadWorkflowOverridden;
use App\Models\Lead;
use App\Models\Workflow;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\postJson;

beforeEach(function () {
  // Allow API requests without real host validation in tests.
  config(['app.postman_auth_enabled' => true, 'services.postman_auth_token' => 'test-token']);
});

// Inline auth header so the file is self-contained (auth.host postman bypass).
function overrideAuthHeader(): array
{
  return ['X-Postman-Auth-Token' => 'test-token'];
}

it('dispatches LeadWorkflowOverridden when workflow_override differs from the intended workflow', function () {
  Queue::fake();
  Event::fake([LeadWorkflowOverridden::class]);

  Lead::factory()->create(['fingerprint' => 'fp-override']);
  $intended = Workflow::factory()->async()->create();
  $effective = Workflow::factory()->async()->create();

  postJson(
    "/v1/share-leads/dispatch/{$effective->id}",
    [
      'fingerprint' => 'fp-override',
      'workflow_override' => [
        'id_intended' => (string) $intended->id,
        'id_effective' => (string) $effective->id,
      ],
    ],
    overrideAuthHeader(),
  )->assertStatus(202);

  Event::assertDispatched(
    LeadWorkflowOverridden::class,
    fn(LeadWorkflowOverridden $e) => $e->idIntended === (string) $intended->id &&
      $e->idEffective === (string) $effective->id &&
      $e->fingerprint === 'fp-override',
  );
});

it('does not dispatch LeadWorkflowOverridden when workflow_override is absent', function () {
  Queue::fake();
  Event::fake([LeadWorkflowOverridden::class]);

  Lead::factory()->create(['fingerprint' => 'fp-plain']);
  $workflow = Workflow::factory()->async()->create();

  postJson("/v1/share-leads/dispatch/{$workflow->id}", ['fingerprint' => 'fp-plain'], overrideAuthHeader())->assertStatus(202);

  Event::assertNotDispatched(LeadWorkflowOverridden::class);
});

it('returns 422 when workflow_override is present but missing id_effective', function () {
  $workflow = Workflow::factory()->async()->create();

  postJson(
    "/v1/share-leads/dispatch/{$workflow->id}",
    [
      'fingerprint' => 'fp-x',
      'workflow_override' => ['id_intended' => (string) $workflow->id],
    ],
    overrideAuthHeader(),
  )
    ->assertStatus(422)
    ->assertJsonValidationErrors(['workflow_override.id_effective']);
});
