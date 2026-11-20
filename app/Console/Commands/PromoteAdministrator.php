<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PromoteAdministrator extends Command
{
    protected $signature = 'app:promote-admin {email? : Existing account email}';

    protected $description = 'Grant administrator access to an existing inventory account';

    public function handle(): int
    {
        $connection = DB::connection();
        $isolated = app()->runningUnitTests() && $connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:';
        if (! $isolated && (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true) || $connection->getDatabaseName() !== 'inventory_management' || $connection->selectOne('SELECT DATABASE() AS name')->name !== 'inventory_management')) {
            $this->error('Refusing to change an account outside inventory_management.');

            return self::FAILURE;
        }
        if (! Schema::hasColumn('users', 'is_admin')) {
            $this->error('Apply the pending inventory migrations first.');

            return self::FAILURE;
        }
        if (! $this->input->isInteractive()) {
            $this->error('Run interactively to confirm administrator access.');

            return self::FAILURE;
        }
        $email = mb_strtolower(trim((string) ($this->argument('email') ?? $this->ask('Existing account email'))));
        $user = User::where('email', $email)->first();
        if ($user === null) {
            $this->error('Account not found. No changes made.');

            return self::FAILURE;
        }
        if (! $this->confirm('Grant administrator access to '.$user->email.' in inventory_management?', false)) {
            return self::FAILURE;
        }
        $user->is_admin = true;
        $user->save();
        $this->info('Administrator access granted. The password is unchanged.');

        return self::SUCCESS;
    }
}
