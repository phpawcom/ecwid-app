<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('admin:user {email} {--name= : Display name}')]
#[Description('Create an admin user, or reset the password of an existing one')]
class AdminUserCommand extends Command
{
    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $password = $this->secret('Password (at least 12 characters)');

        $validator = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $this->option('name') ?? $user->name ?? strstr($email, '@', true);
        $user->password = $password;
        $user->save();

        $this->components->info(($user->wasRecentlyCreated ? 'Created' : 'Updated')." admin user $email.");

        return self::SUCCESS;
    }
}
