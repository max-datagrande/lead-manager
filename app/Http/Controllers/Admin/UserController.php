<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Mostrar la lista de usuarios.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::all(),
        ]);
    }

    /**
     * Mostrar el formulario para crear un nuevo usuario.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create');
    }

    /**
     * Almacenar un nuevo usuario.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,manager,user',
            'is_approved' => 'boolean',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_approved' => $validated['is_approved'] ?? false,
        ]);

        addFlashMessage('success', 'User created correctly.');

        return redirect()->route('admin.users.index');
    }

    /**
     * Mostrar el formulario para editar un usuario.
     */
    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
        ]);
    }

    /**
     * Actualizar un usuario existente.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => 'required|in:admin,manager,user',
            'is_approved' => 'boolean',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_approved' => $validated['is_approved'] ?? false,
        ]);

        if (isset($validated['password']) && $validated['password']) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }
        addFlashMessage('success', 'User updated correctly.');

        return redirect()->route('admin.users.index');
    }

    /**
     * Eliminar un usuario.
     */
    public function destroy(Request $request, User $user)
    {
        // Evitar que un usuario se elimine a sí mismo
        if ($user->id === $request->user()->id()) {
            addFlashMessage('error', 'You cannot delete your own user.');

            return redirect()->route('admin.users.index');
        }

        $user->delete();

        addFlashMessage('success', 'User successfully deleted.');

        return redirect()->route('admin.users.index');
    }
}
