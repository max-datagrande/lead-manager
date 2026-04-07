<?php

use App\Enums\PostbackSource;
use App\Jobs\DispatchPostbackJob;
use App\Models\Postback;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

beforeEach(function () {
  $this->user = User::factory()->create();
});

it('renders the fire form for an internal postback', function () {
  $postback = Postback::factory()->internal()->create();

  actingAs($this->user)
    ->get(route('postbacks.internal.fire-form', $postback))
    ->assertOk()
    ->assertInertia(fn ($page) => $page->component('postbacks/internal-fire'));
});

it('fires an internal postback via manual trigger', function () {
  Queue::fake();
  $postback = Postback::factory()->internal()->create();

  actingAs($this->user)
    ->post(route('postbacks.internal.fire', $postback), [
      'click_id' => 'CLK-MANUAL',
      'amount' => '10.00',
    ])
    ->assertRedirect(route('postbacks.index'));

  $this->assertDatabaseHas('postback_executions', [
    'postback_id' => $postback->id,
    'source' => PostbackSource::MANUAL->value,
  ]);

  Queue::assertPushed(DispatchPostbackJob::class);
});

it('requires authentication to access fire form', function () {
  $postback = Postback::factory()->internal()->create();

  $this->get(route('postbacks.internal.fire-form', $postback))
    ->assertRedirect(route('login'));
});
