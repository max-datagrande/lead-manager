<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

test('admin can see users index page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->count(2)->create();

    $response = actingAs($admin)->get(route('admin.users.index'));

    $response->assertSuccessful();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('admin/users/index')
            ->has('users', 3)
            ->has('roles', 3)
    );
});

test('admin can create a user and send reset password notification', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => 'admin']);

    $response = actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Invited User',
        'email' => 'invited@example.com',
        'role' => 'manager',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'invited@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe('manager');
    expect($user->is_active)->toBeTrue();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('admin can update a user role and active state', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user', 'is_active' => true]);

    $response = actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => 'Updated Name',
        'email' => $user->email,
        'role' => 'manager',
        'is_active' => false,
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->name)->toBe('Updated Name');
    expect($user->role)->toBe('manager');
    expect($user->is_active)->toBeFalse();
});

test('destroy route deactivates user instead of deleting', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['is_active' => true]);

    $response = actingAs($admin)->delete(route('admin.users.destroy', $user));

    $response->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->is_active)->toBeFalse();
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

test('admin cannot deactivate own user', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $response = actingAs($admin)->delete(route('admin.users.destroy', $admin));

    $response->assertRedirect(route('admin.users.index'));

    $admin->refresh();

    expect($admin->is_active)->toBeTrue();
});

test('non admin cannot access users admin section', function () {
    $user = User::factory()->create(['role' => 'user']);

    $response = actingAs($user)->get(route('admin.users.index'));

    $response->assertRedirect(route('home'));
});
