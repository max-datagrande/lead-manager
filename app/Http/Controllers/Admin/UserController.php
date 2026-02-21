<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Mostrar la lista de usuarios.
     */
    public function index(): Response
    {
        return Inertia::render('admin/users/index', [
            'users' => User::query()->orderByDesc('created_at')->get(),
            'roles' => User::$roles,
        ]);
    }

    /**
     * Almacenar un nuevo usuario.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Str::random(64),
            'role' => $validated['role'],
            'is_active' => $request->boolean('is_active', true),
            'is_approved' => true,
        ]);

        Password::sendResetLink(['email' => $user->email]);

        add_flash_message(type: 'success', message: 'User created and invitation email sent successfully.');

        return to_route('admin.users.index');
    }

    /**
     * Actualizar un usuario existente.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        add_flash_message(type: 'success', message: 'User updated successfully.');

        return to_route('admin.users.index');
    }

    /**
     * Desactivar usuario.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            add_flash_message(type: 'error', message: 'You cannot deactivate your own user.');

            return to_route('admin.users.index');
        }

        $user->update(['is_active' => false]);

        add_flash_message(type: 'success', message: 'User deactivated successfully.');

        return to_route('admin.users.index');
    }
}
