<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Requests\Api\V1\Customer\SendCustomerOtpRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerProfileRequest;
use App\Http\Requests\Api\V1\Customer\VerifyCustomerOtpRequest;
use App\Http\Requests\Api\V1\Mobile\SendMobileOtpRequest;
use App\Http\Requests\Api\V1\Mobile\VerifyMobileOtpRequest;
use App\Models\User;
use App\Support\ImagePath;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Customer-facing identity API operations. */
class CustomerIdentityController extends CustomerApiControllerBase
{
    /** Issue a rate-limited customer login or registration OTP. */
    public function sendOtp(SendCustomerOtpRequest $request): JsonResponse
    {
        return $this->issueOtp($request, false);
    }

    /** Issue a rate-limited OTP through the dedicated mobile-app API. */
    public function sendMobileOtp(SendMobileOtpRequest $request): JsonResponse
    {
        return $this->issueOtp($request, true);
    }

    /** Verify a customer OTP and issue a customer-web Sanctum token. */
    public function verifyOtp(VerifyCustomerOtpRequest $request): JsonResponse
    {
        return $this->verifyCustomerOtp($request, false);
    }

    /** Verify a customer OTP and issue a mobile-only Sanctum token. */
    public function verifyMobileOtp(VerifyMobileOtpRequest $request): JsonResponse
    {
        return $this->verifyCustomerOtp($request, true);
    }

    /** Apply the shared customer OTP issue policy to the selected client. */
    private function issueOtp(SendCustomerOtpRequest $request, bool $isMobileClient): JsonResponse
    {
        $validated = $request->validated();
        $mobileNumber = (string) $validated['mobile_number'];
        $clientPrefix = $isMobileClient ? 'mobile-customer-otp:' : 'customer-otp:';
        $limiterKey = $clientPrefix.$mobileNumber.'|'.$request->ip();
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

    /** Verify the latest OTP and issue a token scoped to the requesting client. */
    private function verifyCustomerOtp(
        VerifyCustomerOtpRequest $request,
        bool $isMobileClient
    ): JsonResponse {
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

        $tokenName = $isMobileClient ? 'dwarkesh-mobile' : 'customer-web';
        $tokenAbilities = $isMobileClient
            ? ['mobile-customer-access', 'mobile:read', 'mobile:write']
            : ['customer-access'];
        $token = $user->createToken($tokenName, $tokenAbilities)->plainTextToken;

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
        $previousAvatarPath = $user->avatar_path;
        $avatarPath = $previousAvatarPath;
        $newAvatarPath = null;
        $disk = config('imagepath.disk', 'public');

        if ($request->hasFile('avatar')) {
            $newAvatarPath = $request->file('avatar')->store(config('imagepath.directories.user_avatars'), $disk);

            if (! is_string($newAvatarPath) || $newAvatarPath === '') {
                Log::error('Customer profile photo could not be stored.', [
                    'user_id' => $user->id,
                    'disk' => $disk,
                ]);

                return $this->errorResponse('Your photo could not be saved. Please try again.', [], [], 500);
            }

            $avatarPath = $newAvatarPath;
        }

        try {
            DB::transaction(function () use ($user, $validated, $avatarPath): void {
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
            });
        } catch (Throwable $exception) {
            if ($newAvatarPath) {
                Storage::disk($disk)->delete($newAvatarPath);
            }

            throw $exception;
        }

        if ($newAvatarPath && $previousAvatarPath) {
            Storage::disk($disk)->delete($previousAvatarPath);
        }

        return $this->successResponse('Profile updated successfully.', [
            'name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'avatar_url' => ImagePath::url($avatarPath),
            'preferred_language' => $validated['preferred_language'] ?? 'gu',
        ]);
    }
}
