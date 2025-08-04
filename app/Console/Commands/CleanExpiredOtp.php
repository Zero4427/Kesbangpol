<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Akses\PasswordResetOtp;

class CleanupExpiredOtp extends Command
{
    protected $signature = 'otp:cleanup {--force : Force cleanup without confirmation}';
    protected $description = 'Cleanup expired OTP records';

    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('Are you sure you want to cleanup expired OTP records?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->info('Starting OTP cleanup...');

        // Deactivate expired OTPs
        $deactivatedCount = PasswordResetOtp::where('expires_at', '<', now())
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Delete very old OTP records (older than 30 days)
        $deletedCount = PasswordResetOtp::where('created_at', '<', now()->subDays(30))->delete();

        $this->info("Cleanup completed:");
        $this->line("- Deactivated expired OTPs: {$deactivatedCount}");
        $this->line("- Deleted old records: {$deletedCount}");

        return 0;
    }
}