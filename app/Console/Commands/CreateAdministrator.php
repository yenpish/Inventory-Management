<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-admin')]
#[Description('Create an inventory account using private password prompts')]
class CreateAdministrator extends Command
{
    public function handle(): int
    {
        $connection = DB::connection();
        $isolatedTest = app()->runningUnitTests()
            && $connection->getDriverName() === 'sqlite'
            && $connection->getDatabaseName() === ':memory:';

        if (! $isolatedTest && (
            ! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
            || $connection->getDatabaseName() !== 'inventory_management'
            || $connection->selectOne('SELECT DATABASE() AS name')->name !== 'inventory_management'
        )) {
            $this->error('Refusing to create an account outside inventory_management.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively; passwords must be entered through hidden prompts.');

            return self::FAILURE;
        }

        $this->info('Target database: '.$connection->getDatabaseName());
        $name = $this->ask('Name');
        $email = Str::lower(trim((string) $this->ask('Email')));
        $password = $this->secret('Password', false);
        $confirmation = $this->secret('Confirm password', false);
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols(),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('The password must not exceed 72 bytes.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (! Schema::hasColumn('users', 'is_admin')) {
            $this->error('Apply the pending inventory migrations first.');

            return self::FAILURE;
        }
        $user = new User($validator->safe()->only(['name', 'email', 'password']));
        $user->is_admin = true;
        $user->save();
        $this->info('Inventory account created. The user can now sign in.');

        return self::SUCCESS;
    }
}
