<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendOtpEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $otpCode;
    protected $name;
    protected $expiresInMinutes;

    public $tries = 3;
    public $timeout = 30;

    public function __construct(string $email, string $otpCode, string $name, int $expiresInMinutes = 2)
    {
        $this->email = $email;
        $this->otpCode = $otpCode;
        $this->name = $name;
        $this->expiresInMinutes = $expiresInMinutes;
    }

    public function handle()
    {
        try {
            Mail::send('emails.otp', [
                'name' => $this->name,
                'otp_code' => $this->otpCode,
                'expires_in' => $this->expiresInMinutes . ' minutes'
            ], function ($message) {
                $message->to($this->email)
                        ->subject('Your OTP Code - INDOMAS');
            });

            Log::info('OTP email sent successfully', [
                'email' => $this->email,
                'otp_partial' => substr($this->otpCode, 0, 2) . '****'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email', [
                'email' => $this->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail($e);
        }
    }
}