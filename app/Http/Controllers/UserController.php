<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Menampilkan seluruh user.
     */
    public function index()
    {
        $users = $this->userService->getAll();

        return response()->json([
            'message' => 'Data user berhasil diambil.',
            'data' => $users,
        ]);
    }

    /**
     * Menampilkan detail user.
     */
    public function show(User $user)
    {
        return response()->json([
            'message' => 'Data user berhasil diambil.',
            'data' => $user,
        ]);
    }

    /**
     * Admin membuat user baru.
     */
    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'User berhasil ditambahkan.',
            'data' => $user,
        ], 201);
    }

    /**
     * Admin mengubah user.
     */
    public function update(
        UpdateUserRequest $request,
        User $user
    ) {
        $user = $this->userService->update(
            $user,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'data' => $user,
        ]);
    }

    /**
     * Admin menghapus user.
     */
    public function destroy(
        Request $request,
        User $user
    ) {
        $this->userService->delete(
            $user,
            $request->user()
        );

        return response()->json([
            'message' => 'User berhasil dihapus.',
        ]);
    }
}