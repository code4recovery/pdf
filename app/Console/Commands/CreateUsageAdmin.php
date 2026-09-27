<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUsageAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:admin {email : The admin\'s email address}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update an admin user for the usage dashboard';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $name = text(label: 'Name', required: true);

        $password = password(
            label: 'Password',
            validate: fn (string $value): ?string => strlen($value) < 12
                ? 'Password must be at least 12 characters.'
                : null,
        );

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );

        $this->info(sprintf('Admin user "%s" is ready.', $email));

        return self::SUCCESS;
    }
}
