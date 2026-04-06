<?php

use App\Enums\ExecutionStatus;
use App\Enums\PostbackSource;
use App\Enums\PostbackType;
use App\Jobs\DispatchPostbackJob;
use App\Models\Postback;
use App\Models\PostbackExecution;
use App\Services\PostbackFireService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
  Queue::fake();
});

describe('fireInternal', function () {
  it('creates an execution with the correct source and source_reference', function () {
    $postback = Postback::factory()->internal()->create();
    $service = app(PostbackFireService::class);

    $execution = $service->fireInternal(
      uuid: $postback->uuid,
      params: ['click_id' => 'CLK-001', 'amount' => '5.00'],
      source: PostbackSource::OFFERWALL,
      sourceReference: 'conversion-42',
    );

    expect($execution->source)->toBe(PostbackSource::OFFERWALL);
    expect($execution->source_reference)->toBe('conversion-42');
    expect($execution->status)->toBe(ExecutionStatus::PENDING);
  });

  it('always dispatches via job regardless of fire_mode', function () {
    $postback = Postback::factory()->internal()->realtime()->create();
    $service = app(PostbackFireService::class);

    $service->fireInternal(
      uuid: $postback->uuid,
      params: ['click_id' => 'CLK-001'],
      source: PostbackSource::MANUAL,
    );

    Queue::assertPushed(DispatchPostbackJob::class);
  });

  it('increments total_executions and updates last_fired_at', function () {
    $postback = Postback::factory()->internal()->create();
    $service = app(PostbackFireService::class);

    $service->fireInternal(
      uuid: $postback->uuid,
      params: ['click_id' => 'CLK-001'],
      source: PostbackSource::SYSTEM,
    );

    $postback->refresh();
    expect($postback->total_executions)->toBe(1);
    expect($postback->last_fired_at)->not->toBeNull();
  });

  it('returns existing execution for duplicate idempotency key (same source + params)', function () {
    $postback = Postback::factory()->internal()->create();
    $service = app(PostbackFireService::class);
    $params = ['click_id' => 'CLK-DUP'];

    $first = $service->fireInternal($postback->uuid, $params, PostbackSource::OFFERWALL);
    $second = $service->fireInternal($postback->uuid, $params, PostbackSource::OFFERWALL);

    expect($first->id)->toBe($second->id);
    $this->assertDatabaseCount('postback_executions', 1);
  });

  it('creates separate executions for same params but different sources', function () {
    $postback = Postback::factory()->internal()->create();
    $service = app(PostbackFireService::class);
    $params = ['click_id' => 'CLK-SAME'];

    $fromOfferwall = $service->fireInternal($postback->uuid, $params, PostbackSource::OFFERWALL);
    $fromPingPost = $service->fireInternal($postback->uuid, $params, PostbackSource::PING_POST);

    expect($fromOfferwall->id)->not->toBe($fromPingPost->id);
    $this->assertDatabaseCount('postback_executions', 2);
  });

  it('throws exception when postback is inactive', function () {
    $postback = Postback::factory()->internal()->inactive()->create();
    $service = app(PostbackFireService::class);

    $service->fireInternal($postback->uuid, ['click_id' => 'X'], PostbackSource::MANUAL);
  })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

  it('throws exception when postback has no result_url', function () {
    $postback = Postback::factory()->internal()->withoutResultUrl()->create();
    $service = app(PostbackFireService::class);

    $service->fireInternal($postback->uuid, ['click_id' => 'X'], PostbackSource::MANUAL);
  })->throws(\InvalidArgumentException::class);
});

describe('handleInbound stores external_api source', function () {
  it('sets source to external_api on inbound execution', function () {
    $postback = Postback::factory()->realtime()->create();
    $service = app(PostbackFireService::class);

    $execution = $service->handleInbound(
      uuid: $postback->uuid,
      inboundParams: ['click_id' => 'CLK-EXT'],
    );

    expect($execution->source)->toBe(PostbackSource::EXTERNAL_API);
  });
});
