<?php

namespace App\Console\Commands\User;

use Illuminate\Console\Command;
use App\Models\User;

class UserAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:user-admin {email}';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error('User not found with email: ' . $email);
            return;
        }
        $user->is_admin = 1;
        $user->save();
        $this->info('User ' . $email . ' is now an admin');
    }
}
