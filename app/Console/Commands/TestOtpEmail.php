<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use App\Jobs\SendOtpEmail;

class TestOtpEmail extends Command
{
    protected $signature = 'otp:test-email {email?}';
    protected $description = 'Test OTP email configuration';

    public function handle()
    {
        $email = $this->argument('email') ?? 'test@example.com';
        
        $this->info('Testing email configuration...');
        $this->info("Sending test email to: {$email}");

        try {
            SendOtpEmail::dispatch(
                $email,
                '123456',
                'Test User',
                config('otp.expiry_minutes', 2)
            );

            $this->info('Test email queued successfully!');
            
            $this->info('Testing SMTP connection...');
            $this->testSmtpConnection();

        } catch (\Exception $e) {
            $this->error('Test email failed!');
            $this->error('Error: ' . $e->getMessage());
            
            Log::error('Test email command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->info('Current email configuration:');
            $this->line('MAIL_MAILER: ' . config('mail.default'));
            $this->line('MAIL_HOST: ' . config('mail.mailers.smtp.host'));
            $this->line('MAIL_PORT: ' . config('mail.mailers.smtp.port'));
            $this->line('MAIL_USERNAME: ' . config('mail.mailers.smtp.username'));
            $this->line('MAIL_ENCRYPTION: ' . config('mail.mailers.smtp.encryption'));
            $this->line('MAIL_FROM_ADDRESS: ' . config('mail.from.address'));
        }

        return 0;
    }

    protected function testSmtpConnection()
    {
        try {
            $host = config('mail.mailers.smtp.host');
            $port = config('mail.mailers.smtp.port');
            $username = config('mail.mailers.smtp.username');
            $password = config('mail.mailers.smtp.password');
            $encryption = config('mail.mailers.smtp.encryption');

            $transport = new EsmtpTransport($host, $port, $encryption === 'ssl' ? true : false);

            if ($username && $password) {
                $transport->setUsername($username);
                $transport->setPassword($password);
            }

            $transport->start();

            $this->info('SMTP connection successful!');
            
            $transport->stop();

        } catch (TransportExceptionInterface $e) {
            $this->error('SMTP connection failed: ' . $e->getMessage());
            Log::error('SMTP connection test failed', [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } catch (\Exception $e) {
            $this->error('Unexpected error during SMTP connection test: ' . $e->getMessage());
            Log::error('Unexpected SMTP connection error', [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}