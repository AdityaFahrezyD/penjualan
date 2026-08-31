<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function getAll()
    {
        return User::query()
            ->orderBy('name')
            ->get();
    }

    public function getById(string $id): User
    {
        return User::findOrFail($id);
    }

    public function create(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
        ]);
    }

    public function update(
        User $user,
        array $data,
        ?User $authenticatedUser = null
    ): User {
        /*
         * Mencegah admin mengubah role dirinya sendiri.
         */
        if (
            $authenticatedUser &&
            $authenticatedUser->id === $user->id &&
            array_key_exists('role', $data) &&
            $data['role'] !== $user->role
        ) {
            throw ValidationException::withMessages([
                'role' => [
                    'Anda tidak dapat mengubah role akun sendiri.',
                ],
            ]);
        }

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }

        if (isset($data['email'])) {
            $user->email = $data['email'];
        }

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if (isset($data['role'])) {
            $user->role = $data['role'];
        }

        $user->save();

        return $user->fresh();
    }

    public function delete(
        User $user,
        ?User $authenticatedUser = null
    ): void {
        /*
         * Admin tidak boleh menghapus dirinya sendiri.
         */
        if (
            $authenticatedUser &&
            $authenticatedUser->id === $user->id
        ) {
            throw ValidationException::withMessages([
                'user' => [
                    'Anda tidak dapat menghapus akun sendiri.',
                ],
            ]);
        }

        $user->delete();
    }
}