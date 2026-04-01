<?php

use App\Jobs\PingPost\DispatchLeadJob;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Workflow;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\postJson;

// Helper: make an allowed host header so auth.host passes
function hostHeader(): array
{
    // In tests the middleware is usually bypassed; if not, add the test host.
    return ['X-Postman-Auth-Token' => config('services.postman_auth_token', 'test-token')];
}

beforeEach(function () {
    // Allow API requests without real host validation in tests
    config(['app.postman_auth_enabled' => true, 'services.postman_auth_token' => 'test-token']);
});

// ─── Sync dispatch ────────────────────────────────────────────────────────────

it('synchronously dispatches a lead and returns the dispatch status', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-api-sync']);
    $buyer = Integration::factory()->pingPost()->withBuyerConfig()->create();
    $workflow = Workflow::factory()->bestBid()->create(['execution_mode' => 'sync']);

    \App\Models\WorkflowBuyer::create([
        'workflow_id' => $workflow->id,
        'integration_id' => $buyer->id,
        'position' => 0,
        'buyer_group' => 'primary',
        'is_active' => true,
    ]);

    Http::fake([
        'https://buyer.example.com/ping' => Http::response(['accepted' => 'true', 'bid' => 10.0]),
        'https://buyer.example.com/post' => Http::response(['accepted' => 'true']),
    ]);

    postJson("/v1/share-leads/dispatch/{$workflow->id}", ['fingerprint' => 'fp-api-sync'], hostHeader())
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['dispatch_uuid', 'status', 'strategy_used', 'final_price', 'total_duration_ms']]);
});

// ─── Async dispatch ───────────────────────────────────────────────────────────

it('queues a DispatchLeadJob when workflow is async and returns 202', function () {
    Queue::fake();

    $lead = Lead::factory()->create(['fingerprint' => 'fp-api-async']);
    $buyer = Integration::factory()->pingPost()->withBuyerConfig()->create();
    $workflow = Workflow::factory()->bestBid()->async()->create();

    \App\Models\WorkflowBuyer::create([
        'workflow_id' => $workflow->id,
        'integration_id' => $buyer->id,
        'position' => 0,
        'buyer_group' => 'primary',
        'is_active' => true,
    ]);

    postJson("/v1/share-leads/dispatch/{$workflow->id}", ['fingerprint' => 'fp-api-async'], hostHeader())
        ->assertStatus(202)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.queued', true);

    Queue::assertPushed(DispatchLeadJob::class, fn ($job) => $job->workflowId === $workflow->id);
});

// ─── Validation ──────────────────────────────────────────────────────────────

it('returns 422 when neither fingerprint nor lead_id is provided', function () {
    $workflow = Workflow::factory()->create();

    postJson("/v1/share-leads/dispatch/{$workflow->id}", [], hostHeader())
        ->assertStatus(422);
});

it('returns 404 when lead fingerprint does not exist', function () {
    $workflow = Workflow::factory()->create();

    postJson("/v1/share-leads/dispatch/{$workflow->id}", ['fingerprint' => 'does-not-exist'], hostHeader())
        ->assertNotFound();
});

it('returns 404 when workflow does not exist', function () {
    postJson('/v1/share-leads/dispatch/999999', ['fingerprint' => 'x'], hostHeader())
        ->assertNotFound();
});
