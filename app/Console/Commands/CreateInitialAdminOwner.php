<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** Create the first owner interactively from the deployment shell, never from a public API. */
class CreateInitialAdminOwner extends Command
{
    protected $signature = 'admin:create-owner';

    protected $description = 'Interactively create the one-time bakery owner account.';

    /** Require a clean privilege boundary and create a verified owner with a hashed password. */
    public function handle(): int
    {
        if (User::query()->where('role', 'owner')->exists()) {
            $this->components->error('An owner account already exists. Use the staff management UI to create additional admins.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Owner name'));
        $mobile = trim((string) $this->ask('Owner mobile number'));
        $email = trim((string) $this->ask('Owner email (optional)'));
        $password = (string) $this->secret('Password (minimum 12 characters)');
        $confirmation = (string) $this->secret('Confirm password');
        if ($name === '' || $mobile === '' || mb_strlen($password) < 12 || $password !== $confirmation) {
            $this->components->error('Name and mobile are required; password must be at least 12 characters and match confirmation.');

            return self::INVALID;
        }
        if (User::query()->where('mobile_number', $mobile)->exists() || ($email !== '' && User::query()->where('email', $email)->exists())) {
            $this->components->error('That mobile number or email is already registered.');

            return self::INVALID;
        }

        User::query()->create([
            'name' => $name,
            'mobile_number' => $mobile,
            'email' => $email !== '' ? $email : null,
            'password' => Hash::make($password),
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->components->info('Initial owner created. Sign in through the admin portal.');

        return self::SUCCESS;
    }
}
