<?php

namespace App\Console\Commands;

use App\Models\SslSetting;
use App\Services\Ssl\SslManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RenewSslCertificates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ssl:renew';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and renew Let\'s Encrypt certificates if they are close to expiration';

    /**
     * Execute the console command.
     */
    public function handle(SslManager $sslManager): int
    {
        $setting = SslSetting::where('is_enabled', true)->first();

        if (! $setting) {
            $this->info('Auto-SSL is not enabled. Skipping.');
            return self::SUCCESS;
        }

        // Renew if expires in less than 30 days
        if ($setting->expires_at && now()->diffInDays($setting->expires_at) > 30) {
            $this->info('Certificate is still valid for more than 30 days. Skipping.');
            return self::SUCCESS;
        }

        $this->info("Renewing SSL certificate for domains: " . implode(', ', $setting->domains));
        
        if ($sslManager->requestCertificate($setting)) {
            $this->info('SSL certificate renewed successfully.');
            $sslManager->syncToFreeswitch();
            return self::SUCCESS;
        }

        $this->error('Failed to renew SSL certificate: ' . $setting->last_error);
        return self::FAILURE;
    }
}
