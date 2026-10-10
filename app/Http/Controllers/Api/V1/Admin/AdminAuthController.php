<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminLoginRequest;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    use ApiResponseTrait;

    /** Verify an active back-office account and issue a narrowly scoped Sanctum token. */
    public function loginAdmin(AdminLoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $key = Str::lower($data['identifier']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $this->errorResponse('Too many sign-in attempts. Try again in '.RateLimiter::availableIn($key).' seconds.', [], [], 429);
        }
        $user = User::query()
            ->where(function ($query) use ($data) {
                $query->where('mobile_number', $data['identifier'])
                    ->orWhere('email', $data['identifier']);
            })
            ->whereIn('role', AdminRole::managementRoles())
            ->where('is_active', true)
            ->first();
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);

            return $this->errorResponse('The supplied credentials are invalid.', [], [], 401);
        }
        RateLimiter::clear($key);
        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('admin-portal', ['admin-access', 'admin:read', 'admin:write'])->plainTextToken;

        $avatarUrl = $user->avatar_path
            ? Storage::disk(config('imagepath.disk'))->url($user->avatar_path)
            : null;

        return $this->successResponse('Signed in successfully.', [
            'token' => $token,
            'admin' => [
                'name' => $user->name,
                'role' => $user->role,
                'avatar_url' => $avatarUrl,
            ],
        ]);
    }

    /** Revoke the current admin's bearer token. */
    public function logoutAdmin(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->successResponse('Signed out successfully.', []);
    }
}
