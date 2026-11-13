<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

#[Signature('shopar:admin {username : The name the admin signs in with} {--email= : The admin\'s email address (they can sign in with this too)} {--name=Admin : The admin\'s display name} {--password= : The password (asked for when left out)}')]
#[Description('Create an admin user for the dashboard, or reset an existing admin\'s password')]
class CreateAdminUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $user = User::query()->firstOrNew(['username' => $username]);
        $email = (string) ($this->option('email') ?: $user->email ?: "{$username}@shopar.local");
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        $validator = Validator::make(
            ['username' => $username, 'email' => $email, 'password' => $password],
            [
                'username' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
                'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user->forceFill([
            'name' => $user->name ?? (string) $this->option('name'),
            'email' => $email,
            'password' => $password,
            'is_admin' => true,
        ])->save();

        $this->info("{$username} can now sign in at ".route('admin.login'));

        return self::SUCCESS;
    }
}
