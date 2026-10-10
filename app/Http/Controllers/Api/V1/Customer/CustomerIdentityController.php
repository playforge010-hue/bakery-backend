<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Requests\Api\V1\Customer\SendCustomerOtpRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerProfileRequest;
use App\Http\Requests\Api\V1\Customer\VerifyCustomerOtpRequest;
use App\Models\User;
use App\Support\ImagePath;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

/** Customer-facing identity API operations. */
class CustomerIdentityController extends CustomerApiControllerBase
{
    /** Issue a rate-limited customer login or registration OTP. */
    public function sendOtp(SendCustomerOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $mobileNumber = (string) $validated['mobile_number'];
        $limiterKey = 'customer-otp:'.$mobileNumber.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            return $this->errorResponse('Too many OTP requests. Try again later.', [], ['retry_after_seconds' => RateLimiter::availableIn($limiterKey)], 429);
        }
        RateLimiter::hit($limiterKey, 300);
        $purpose = $validated['purpose'] ?? 'login';
        $userExists = DB::table('users')->where('mobile_number', $mobileNumber)->exists();
        if ($purpose === 'register' && $userExists) {
            return $this->errorResponse('An account already exists for this mobile number.', [], [], 409);
        }
        if ($purpose === 'login' && ! $userExists) {
            return $this->errorResponse('No account found. Register first.', [], [], 404);
        }
        $testMobileNumber = (string) config('services.customer_otp.test_mobile_number', '');
        $isTestMobileNumber = $testMobileNumber !== ''
            && hash_equals($testMobileNumber, $mobileNumber);
        $otp = $isTestMobileNumber || app()->environment('local', 'testing')
            ? (string) config('services.customer_otp.test_code', '123456')
            : (string) random_int(100000, 999999);

        if (! $isTestMobileNumber && config('services.customer_otp.driver', 'log') !== 'log') {
            return $this->errorResponse(
                'The configured SMS provider is not implemented. Configure a supported OTP delivery service before enabling production login.',
                [],
                [],
                503
            );
        }

        DB::table('customer_otps')->insert([
            'mobile_number' => $mobileNumber,
            'otp_hash' => Hash::make($otp),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (! $isTestMobileNumber) {
            Log::info('Customer login OTP issued through local log driver.', [
                'mobile_number' => $mobileNumber,
            ]);
        }

        return $this->successResponse('OTP issued successfully.', [
            'mobile_number' => $mobileNumber,
            'otp_expires_in_seconds' => 300,
            'local_testing_otp' => app()->environment('local', 'testing') ? $otp : null,
        ], [], 202);
    }

    /** Verify the latest customer OTP and issue a Sanctum token. */
    public function verifyOtp(VerifyCustomerOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $mobileNumber = (string) $validated['mobile_number'];
        $otp = (string) $validated['otp'];

        $otpRecord = DB::table('customer_otps')
            ->where('mobile_number', $mobileNumber)
            ->whereNull('verified_at')
            ->orderByDesc('id')
            ->first();

        $isOtpExpired = $otpRecord
            && now()->greaterThan(Carbon::parse($otpRecord->expires_at));
        $hasTooManyAttempts = $otpRecord && $otpRecord->attempts >= 5;
        $otpDoesNotMatch = $otpRecord && ! Hash::check($otp, $otpRecord->otp_hash);

        if (! $otpRecord || $hasTooManyAttempts || $isOtpExpired || $otpDoesNotMatch) {
            if ($otpRecord) {
                DB::table('customer_otps')->where('id', $otpRecord->id)->increment('attempts');
            }

            return $this->errorResponse('Invalid or expired OTP.', ['otp' => ['Check the code and try again.']], [], 422);
        }

        DB::table('customer_otps')->where('id', $otpRecord->id)->update(['verified_at' => now(), 'updated_at' => now()]);

        $user = User::where('mobile_number', $mobileNumber)->first();
        if (! $user) {
            $user = User::create([
                'name' => $validated['name'] ?? 'Dwarkesh Customer', 'mobile_number' => $mobileNumber,
                'email' => $validated['email'] ?? null, 'role' => 'customer', 'is_active' => true, 'last_login_at' => now(),
            ]);
            DB::table('customer_profiles')->insert([
                'user_id' => $user->id,
                'full_name' => $validated['name'] ?? 'Dwarkesh Customer',
                'mobile_number' => $mobileNumber,
                'email' => $validated['email'] ?? null,
                'preferred_language' => 'gu',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            if (! $user->is_active || $user->role !== 'customer') {
                return $this->errorResponse('This account is not available for customer login.', [], [], 403);
            }
            $user->forceFill(['last_login_at' => now()])->save();
        }

        $token = $user->createToken('customer-web', ['customer-access'])->plainTextToken;

        return $this->successResponse('OTP verified successfully.', [
            'token' => $token,
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile_number' => $user->mobile_number,
                'avatar_url' => ImagePath::url($user->avatar_path),
                'preferred_language' => 'gu',
            ],
        ]);
    }

    /** Revoke the authenticated customer access token. */
    public function logoutCustomer(): JsonResponse
    {
        request()->user()->currentAccessToken()?->delete();

        return $this->successResponse('Logged out successfully.');
    }

    /** Return the authenticated customer profile. */
    public function getProfile(): JsonResponse
    {
        $user = $this->customerUser();
        $profile = DB::table('customer_profiles')->where('user_id', $user->id)->first();

        return $this->successResponse('Profile loaded successfully.', [
            'id' => $user->id,
            'name' => $user->name,
            'mobile_number' => $user->mobile_number,
            'email' => $user->email,
            'avatar_url' => ImagePath::url($user->avatar_path),
            'preferred_language' => $profile?->preferred_language ?? 'gu',
        ]);
    }

    /** Update customer profile fields and optional avatar image. */
    public function updateProfile(UpdateCustomerProfileRequest $request): JsonResponse
    {
        $user = $this->customerUser();
        $validated = $request->validated();
        $avatarPath = $user->avatar_path;
        if ($request->hasFile('avatar')) {
            $disk = config('imagepath.disk', 'public');
            $newAvatarPath = $request->file('avatar')->store(config('imagepath.directories.user_avatars'), $disk);
            if ($avatarPath) {
                Storage::disk($disk)->delete($avatarPath);
            }
            $avatarPath = $newAvatarPath;
        }
        DB::table('users')->where('id', $user->id)->update([
            'name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'avatar_path' => $avatarPath,
            'updated_at' => now(),
        ]);
        DB::table('customer_profiles')->where('user_id', $user->id)->update([
            'full_name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'preferred_language' => $validated['preferred_language'] ?? 'gu',
            'updated_at' => now(),
        ]);

        return $this->successResponse('Profile updated successfully.', [
            'name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'avatar_url' => ImagePath::url($avatarPath),
            'preferred_language' => $validated['preferred_language'] ?? 'gu',
        ]);
    }
}
