<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingSetting;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class SystemAccountResolver
{
    public function resolve(string $key): Account
    {
        return Cache::remember("accounting.system-account.{$key}", now()->addMinutes(10), function () use ($key) {
            $setting = AccountingSetting::query()
                ->with('account')
                ->where('key', $key)
                ->first();

            if ($setting?->account && $setting->account->is_active) {
                return $setting->account;
            }

            $code = config("accounting.system_accounts.{$key}");
            if (!$code) {
                throw new RuntimeException("No system account mapping exists for [{$key}].");
            }

            $account = Account::query()
                ->where('code', $code)
                ->where('is_active', true)
                ->first();

            if (!$account) {
                throw new RuntimeException("System account [{$key}] with code [{$code}] is not available.");
            }

            AccountingSetting::query()->updateOrCreate(
                ['key' => $key],
                ['account_id' => $account->id],
            );

            return $account;
        });
    }

    public function forget(string $key): void
    {
        Cache::forget("accounting.system-account.{$key}");
    }
}
