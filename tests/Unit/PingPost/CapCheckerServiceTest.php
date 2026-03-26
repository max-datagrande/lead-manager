<?php

use App\Enums\PostResultStatus;
use App\Models\BuyerCapRule;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\PostResult;
use App\Models\Workflow;
use App\Services\PingPost\CapCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ─── No caps ─────────────────────────────────────────────────────────────────

it('returns false (not exceeded) when integration has no cap rules', function () {
    $integration = Integration::factory()->pingPost()->create();

    expect(app(CapCheckerService::class)->isCapExceeded($integration))->toBeFalse();
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeAcceptedPost(Integration $integration, float $price = 10.0, ?Carbon\Carbon $at = null): PostResult
{
    $workflow = Workflow::factory()->create();
    $lead = Lead::factory()->create();
    $dispatch = LeadDispatch::create([
        'workflow_id' => $workflow->id,
        'lead_id' => $lead->id,
        'fingerprint' => fake()->uuid(),
        'status' => 'sold',
        'strategy_used' => 'best_bid',
    ]);

    $result = PostResult::create([
        'lead_dispatch_id' => $dispatch->id,
        'integration_id' => $integration->id,
        'status' => PostResultStatus::ACCEPTED,
        'price_final' => $price,
    ]);

    if ($at) {
        \Illuminate\Support\Facades\DB::table('post_results')->where('id', $result->id)->update(['created_at' => $at]);
    }

    return $result;
}

// ─── Max leads ───────────────────────────────────────────────────────────────

it('returns false when lead count is below cap', function () {
    $integration = Integration::factory()->pingPost()->create();
    BuyerCapRule::create(['integration_id' => $integration->id, 'period' => 'day', 'max_leads' => 5]);
    $integration->load('capRules');

    makeAcceptedPost($integration);
    makeAcceptedPost($integration);

    expect(app(CapCheckerService::class)->isCapExceeded($integration))->toBeFalse();
});

it('returns true when lead count meets the daily cap', function () {
    $integration = Integration::factory()->pingPost()->create();
    BuyerCapRule::create(['integration_id' => $integration->id, 'period' => 'day', 'max_leads' => 2]);
    $integration->load('capRules');

    makeAcceptedPost($integration);
    makeAcceptedPost($integration);

    expect(app(CapCheckerService::class)->isCapExceeded($integration))->toBeTrue();
});

// ─── Max revenue ─────────────────────────────────────────────────────────────

it('returns true when revenue meets the daily cap', function () {
    $integration = Integration::factory()->pingPost()->create();
    BuyerCapRule::create(['integration_id' => $integration->id, 'period' => 'day', 'max_revenue' => 20.00]);
    $integration->load('capRules');

    makeAcceptedPost($integration, 10.00);
    makeAcceptedPost($integration, 10.00);

    expect(app(CapCheckerService::class)->isCapExceeded($integration))->toBeTrue();
});

it('returns false when revenue is below the daily cap', function () {
    $integration = Integration::factory()->pingPost()->create();
    BuyerCapRule::create(['integration_id' => $integration->id, 'period' => 'day', 'max_revenue' => 50.00]);
    $integration->load('capRules');

    makeAcceptedPost($integration, 10.00);

    expect(app(CapCheckerService::class)->isCapExceeded($integration))->toBeFalse();
});

// ─── Period isolation ────────────────────────────────────────────────────────

it('does not count yesterday leads against a daily cap', function () {
    $integration = Integration::factory()->pingPost()->create();
    BuyerCapRule::create(['integration_id' => $integration->id, 'period' => 'day', 'max_leads' => 1]);
    $integration->load('capRules');

    // Post from yesterday — should not count
    makeAcceptedPost($integration, 10.0, Carbon\Carbon::yesterday());

    expect(app(CapCheckerService::class)->isCapExceeded($integration))->toBeFalse();
});

// ─── Multiple rules ───────────────────────────────────────────────────────────

it('returns true if any cap rule is exceeded (OR semantics)', function () {
    $integration = Integration::factory()->pingPost()->create();
    BuyerCapRule::create(['integration_id' => $integration->id, 'period' => 'day', 'max_leads' => 100, 'max_revenue' => 5.00]);
    $integration->load('capRules');

    makeAcceptedPost($integration, 5.00); // revenue hits cap, leads is only 1

    expect(app(CapCheckerService::class)->isCapExceeded($integration))->toBeTrue();
});
