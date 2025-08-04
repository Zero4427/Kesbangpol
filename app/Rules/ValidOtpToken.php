<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Services\OtpService;

class ValidOtpToken implements ValidationRule
{
    private $otpService;

    public function __construct()
    {
        $this->otpService = new OtpService();
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if ($this->otpService->decodeOtpToken($value) === null) {
            $fail('The OTP token is invalid or expired.');
        }
    }
}