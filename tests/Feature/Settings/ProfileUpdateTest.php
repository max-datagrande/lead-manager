<?php

use App\Models\User;

test('profile page is displayed', function () {
  $user = User::factory()->create();

  $response = $this->actingAs($user)->get('/settings/profile');

  $response->assertOk();
});

test('profile information can be updated', function () {
  $user = User::factory()->create();

  $response = $this->actingAs($user)->patch('/settings/profile', [
    'name' => 'Test User',
    'email' => 'test@example.com',
  ]);

  $response->assertSessionHasNoErrors()->assertRedirect('/settings/profile');

  $user->refresh();

  expect($user->name)->toBe('Test User');
  expect($user->email)->toBe('test@example.com');
  expect($user->email_verified_at)->toBeNull();
});

test('timezone can be saved with a supported value', function () {
  $user = User::factory()->create();

  $response = $this->actingAs($user)->patch('/settings/profile', [
    'name' => $user->name,
    'email' => $user->email,
    'timezone' => 'America/New_York',
  ]);

  $response->assertSessionHasNoErrors()->assertRedirect('/settings/profile');

  expect($user->refresh()->timezone)->toBe('America/New_York');
});

test('timezone can be cleared by sending null', function () {
  $user = User::factory()->create(['timezone' => 'America/New_York']);

  $response = $this->actingAs($user)->patch('/settings/profile', [
    'name' => $user->name,
    'email' => $user->email,
    'timezone' => null,
  ]);

  $response->assertSessionHasNoErrors();
  expect($user->refresh()->timezone)->toBeNull();
});

test('timezone outside the supported list is rejected', function () {
  $user = User::factory()->create();

  $response = $this->actingAs($user)
    ->from('/settings/profile')
    ->patch('/settings/profile', [
      'name' => $user->name,
      'email' => $user->email,
      'timezone' => 'Mars/Olympus_Mons',
    ]);

  $response->assertSessionHasErrors('timezone');
});

test('email verification status is unchanged when the email address is unchanged', function () {
  $user = User::factory()->create();

  $response = $this->actingAs($user)->patch('/settings/profile', [
    'name' => 'Test User',
    'email' => $user->email,
  ]);

  $response->assertSessionHasNoErrors()->assertRedirect('/settings/profile');

  expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
  $user = User::factory()->create();

  $response = $this->actingAs($user)->delete('/settings/profile', [
    'password' => 'password',
  ]);

  $response->assertSessionHasNoErrors()->assertRedirect('/');

  $this->assertGuest();
  expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
  $user = User::factory()->create();

  $response = $this->actingAs($user)
    ->from('/settings/profile')
    ->delete('/settings/profile', [
      'password' => 'wrong-password',
    ]);

  $response->assertSessionHasErrors('password')->assertRedirect('/settings/profile');

  expect($user->fresh())->not->toBeNull();
});
