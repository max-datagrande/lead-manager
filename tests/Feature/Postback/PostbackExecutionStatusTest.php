<?php

use App\Models\PostbackExecution;

use function Pest\Laravel\getJson;

it('returns execution status for a valid execution uuid', function () {
  $execution = PostbackExecution::factory()->completed()->create();

  getJson("/v1/postback/execution/{$execution->execution_uuid}")
    ->assertOk()
    ->assertJsonPath('success', true)
    ->assertJsonStructure(['success', 'data' => ['execution_uuid', 'status', 'attempts', 'dispatched_at', 'completed_at']])
    ->assertJsonPath('data.execution_uuid', $execution->execution_uuid)
    ->assertJsonPath('data.status', 'completed');
});

it('returns 404 for unknown execution uuid', function () {
  getJson('/v1/postback/execution/00000000-0000-0000-0000-000000000000')->assertNotFound()->assertJsonPath('success', false);
});
