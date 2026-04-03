<?php

use App\Models\Postback;
use App\Models\PostbackExecution;

use function Pest\Laravel\getJson;

it('fires realtime postback and returns completed execution', function () {
  $postback = Postback::factory()->realtime()->create();

  $response = getJson("/v1/postback/fire/{$postback->uuid}?click_id=CLK-123&payout=9.99");

  $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.status', 'completed');

  expect($response->json('data.execution_uuid'))->not->toBeNull();

  $this->assertDatabaseHas('postback_executions', [
    'postback_id' => $postback->id,
    'status' => 'completed',
  ]);

  $this->assertDatabaseHas('postback_dispatch_logs', [
    'attempt_number' => 1,
    'response_status_code' => 200,
  ]);

  $postback->refresh();
  expect($postback->total_executions)->toBe(1);
  expect($postback->last_fired_at)->not->toBeNull();
});

it('fires deferred postback and returns pending execution without dispatching', function () {
  $postback = Postback::factory()->deferred()->create();

  $response = getJson("/v1/postback/fire/{$postback->uuid}?click_id=CLK-123");

  $response->assertOk()->assertJsonPath('data.status', 'pending');

  $this->assertDatabaseHas('postback_executions', [
    'postback_id' => $postback->id,
    'status' => 'pending',
  ]);

  $this->assertDatabaseCount('postback_dispatch_logs', 0);
});

it('returns 404 when uuid does not exist', function () {
  getJson('/v1/postback/fire/00000000-0000-0000-0000-000000000000')->assertNotFound()->assertJsonPath('success', false);
});

it('returns 404 when postback is inactive', function () {
  $postback = Postback::factory()->inactive()->create();

  getJson("/v1/postback/fire/{$postback->uuid}?click_id=X")->assertNotFound();
});

it('returns 422 when postback has no result_url', function () {
  $postback = Postback::factory()->withoutResultUrl()->create();

  getJson("/v1/postback/fire/{$postback->uuid}?click_id=X")
    ->assertStatus(422)
    ->assertJsonPath('success', false);
});

it('returns the same execution when the same request is fired twice (idempotency)', function () {
  $postback = Postback::factory()->realtime()->create();

  $first = getJson("/v1/postback/fire/{$postback->uuid}?click_id=CLK-123")->json('data.execution_uuid');
  $second = getJson("/v1/postback/fire/{$postback->uuid}?click_id=CLK-123")->json('data.execution_uuid');

  expect($first)->toBe($second);
  $this->assertDatabaseCount('postback_executions', 1);

  $postback->refresh();
  expect($postback->total_executions)->toBe(1);
});

it('creates separate executions when params differ', function () {
  $postback = Postback::factory()->realtime()->create();

  getJson("/v1/postback/fire/{$postback->uuid}?click_id=CLK-111");
  getJson("/v1/postback/fire/{$postback->uuid}?click_id=CLK-222");

  $this->assertDatabaseCount('postback_executions', 2);

  $postback->refresh();
  expect($postback->total_executions)->toBe(2);
});

it('stores ip address and user agent on execution', function () {
  $postback = Postback::factory()->realtime()->create();

  getJson("/v1/postback/fire/{$postback->uuid}?click_id=X", [
    'User-Agent' => 'TestAgent/1.0',
  ]);

  $execution = PostbackExecution::query()->where('postback_id', $postback->id)->firstOrFail();

  expect($execution->ip_address)->not->toBeNull();
  expect($execution->user_agent)->toBe('TestAgent/1.0');
});

it('builds the correct outbound url from param mappings', function () {
  $postback = Postback::factory()
    ->realtime()
    ->create([
      'base_url' => 'https://dest.example.com/cv?click_id=&payout=',
      'param_mappings' => ['click_id' => 'click_id', 'payout' => 'payout'],
      'result_url' => 'https://dest.example.com/cv?click_id={click_id}&payout={payout}',
    ]);

  getJson("/v1/postback/fire/{$postback->uuid}?click_id=ABC&payout=5.00");

  $execution = PostbackExecution::query()->where('postback_id', $postback->id)->firstOrFail();

  expect($execution->outbound_url)->toBe('https://dest.example.com/cv?click_id=ABC&payout=5.00');
});

it('returns the correct response shape', function () {
  $postback = Postback::factory()->realtime()->create();

  getJson("/v1/postback/fire/{$postback->uuid}?click_id=X")
    ->assertOk()
    ->assertJsonStructure(['success', 'data' => ['execution_uuid', 'status'], 'message']);
});
