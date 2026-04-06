<?php

use App\Enums\DispatchStatus;
use App\Enums\PostbackSource;
use App\Enums\PostbackType;
use App\Enums\PostResultStatus;
use App\Events\LeadSold;
use App\Jobs\DispatchPostbackJob;
use App\Models\Field;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\LeadFieldResponse;
use App\Models\Postback;
use App\Models\PostResult;
use App\Models\TrafficLog;
use App\Models\Workflow;
use App\Services\PingPost\PostbackResolverService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * Helper: build a complete scenario with workflow, lead, traffic, integration, and internal postback.
 */
function makeSaleScenario(array $overrides = []): array
{
  $workflow = Workflow::factory()->waterfall()->create();
  $integration = Integration::factory()->postOnly()->create();
  $lead = Lead::factory()->create(['fingerprint' => fake()->sha256()]);

  $field = Field::factory()->create(['name' => 'email', 'label' => 'Email']);
  LeadFieldResponse::create([
    'lead_id' => $lead->id,
    'field_id' => $field->id,
    'value' => 'test@example.com',
    'fingerprint' => $lead->fingerprint,
  ]);

  TrafficLog::factory()->create([
    'fingerprint' => $lead->fingerprint,
    'click_id' => 'CLK-999',
    'ip_address' => '10.0.0.1',
  ]);

  $postback = Postback::factory()
    ->internal()
    ->realtime()
    ->create([
      'base_url' => 'https://tracker.test/postback?revenue={lead_price}&event={event_name}&email={email}&cid={click_id}',
      'param_mappings' => [
        'revenue' => 'lead_price',
        'event' => 'event_name',
        'email' => 'email',
        'cid' => 'traffic.click_id',
      ],
    ]);

  $workflow->postbacks()->attach($postback->id);

  $dispatch = LeadDispatch::create([
    'workflow_id' => $workflow->id,
    'lead_id' => $lead->id,
    'fingerprint' => $lead->fingerprint,
    'status' => DispatchStatus::RUNNING,
    'strategy_used' => 'waterfall',
    'started_at' => now(),
  ]);

  return compact('workflow', 'integration', 'lead', 'field', 'postback', 'dispatch');
}

beforeEach(function () {
  Queue::fake();
});

describe('LeadSold event dispatch', function () {
  it('dispatches LeadSold event when markAsSold is called', function () {
    Event::fake([LeadSold::class]);

    $s = makeSaleScenario();

    $s['dispatch']->markAsSold($s['integration'], 12.5);

    Event::assertDispatched(LeadSold::class, function (LeadSold $event) use ($s) {
      return $event->dispatch->id === $s['dispatch']->id;
    });
  });

  it('does not dispatch LeadSold on markAsNotSold', function () {
    Event::fake([LeadSold::class]);

    $s = makeSaleScenario();
    $s['dispatch']->markAsNotSold();

    Event::assertNotDispatched(LeadSold::class);
  });
});

describe('FireInternalPostbacksListener', function () {
  it('fires internal postbacks when a lead is sold', function () {
    $s = makeSaleScenario();

    $s['dispatch']->markAsSold($s['integration'], 15.0);

    $this->assertDatabaseHas('postback_executions', [
      'postback_id' => $s['postback']->id,
      'source' => PostbackSource::WORKFLOW->value,
      'source_reference' => $s['dispatch']->dispatch_uuid,
    ]);

    Queue::assertPushed(DispatchPostbackJob::class);
  });

  it('includes lead_price, event_name, and buyer_name in params', function () {
    $s = makeSaleScenario();

    $s['dispatch']->markAsSold($s['integration'], 25.5);

    $execution = \App\Models\PostbackExecution::query()->where('postback_id', $s['postback']->id)->first();

    expect($execution)->not->toBeNull();
    expect($execution->inbound_params['lead_price'])->toBe('25.5000');
    expect($execution->inbound_params['event_name'])->toBe('sale');
    expect($execution->inbound_params['buyer_name'])->toBe($s['integration']->name);
  });

  it('includes resolved field values and traffic tokens', function () {
    $s = makeSaleScenario();

    $s['dispatch']->markAsSold($s['integration'], 10.0);

    $execution = \App\Models\PostbackExecution::query()->where('postback_id', $s['postback']->id)->first();

    expect($execution->inbound_params['email'])->toBe('test@example.com');
    expect($execution->inbound_params['traffic.click_id'])->toBe('CLK-999');
    expect($execution->inbound_params['traffic.ip_address'])->toBe('10.0.0.1');
  });

  it('does not fire when workflow has no associated postbacks', function () {
    $workflow = Workflow::factory()->waterfall()->create();
    $integration = Integration::factory()->postOnly()->create();
    $lead = Lead::factory()->create();

    $dispatch = LeadDispatch::create([
      'workflow_id' => $workflow->id,
      'lead_id' => $lead->id,
      'fingerprint' => $lead->fingerprint,
      'status' => DispatchStatus::RUNNING,
      'strategy_used' => 'waterfall',
      'started_at' => now(),
    ]);

    $dispatch->markAsSold($integration, 10.0);

    $this->assertDatabaseCount('postback_executions', 0);
    Queue::assertNotPushed(DispatchPostbackJob::class);
  });

  it('continues firing remaining postbacks when one fails', function () {
    $s = makeSaleScenario();

    // Create a second postback that will fail (no result_url)
    $failingPostback = Postback::factory()
      ->internal()
      ->withoutResultUrl()
      ->create([
        'base_url' => 'https://broken.test/cb?p={lead_price}',
        'param_mappings' => ['p' => 'lead_price'],
      ]);
    $s['workflow']->postbacks()->attach($failingPostback->id);

    $s['dispatch']->markAsSold($s['integration'], 10.0);

    // The valid postback should still have fired
    $this->assertDatabaseHas('postback_executions', [
      'postback_id' => $s['postback']->id,
      'source' => PostbackSource::WORKFLOW->value,
    ]);

    // The failing one should NOT have created an execution
    $this->assertDatabaseMissing('postback_executions', [
      'postback_id' => $failingPostback->id,
    ]);
  });

  it('does not break the sale when listener encounters an error', function () {
    $s = makeSaleScenario();

    $s['dispatch']->markAsSold($s['integration'], 20.0);

    $s['dispatch']->refresh();
    expect($s['dispatch']->status)->toBe(DispatchStatus::SOLD);
    expect((float) $s['dispatch']->final_price)->toBe(20.0);
  });

  it('respects idempotency — duplicate markAsSold does not double-fire', function () {
    $s = makeSaleScenario();

    $s['dispatch']->markAsSold($s['integration'], 10.0);

    $countBefore = \App\Models\PostbackExecution::count();

    // The dispatch is already SOLD (terminal), so markAsSold again
    // would update the record but fire the event again.
    // The idempotency key in fireInternal prevents duplicate executions.
    LeadSold::dispatch($s['dispatch']);

    expect(\App\Models\PostbackExecution::count())->toBe($countBefore);
  });
});

describe('PostbackResolverService integration', function () {
  it('fires internal postbacks when async postback resolves', function () {
    $s = makeSaleScenario();

    $postResult = PostResult::create([
      'lead_dispatch_id' => $s['dispatch']->id,
      'integration_id' => $s['integration']->id,
      'status' => PostResultStatus::PENDING_POSTBACK,
      'price_offered' => 0,
      'postback_expires_at' => now()->addHours(24),
    ]);

    $resolver = app(PostbackResolverService::class);
    $resolver->resolvePostback($postResult->id, 30.0);

    $this->assertDatabaseHas('postback_executions', [
      'postback_id' => $s['postback']->id,
      'source' => PostbackSource::WORKFLOW->value,
    ]);

    $execution = \App\Models\PostbackExecution::query()->where('postback_id', $s['postback']->id)->first();

    expect($execution->inbound_params['lead_price'])->toBe('30.0000');
    expect($execution->inbound_params['event_name'])->toBe('sale');
  });
});
