<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class RecordSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (!method_exists($user, 'forceFill')) {
            return;
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()?->ip(),
            'login_count' => ((int) ($user->login_count ?? 0)) + 1,
        ])->save();
    }
}
