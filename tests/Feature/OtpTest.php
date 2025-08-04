<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Akses\Pengguna;
use App\Models\Akses\PasswordResetOtp;
use App\Jobs\SendOtpEmail;
use Illuminate\Mail\Mailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;

class OtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_request_otp()
    {
        Mail::fake();
        
        $user = Pengguna::factory()->create([
            'email_pengguna' => 'test@example.com'
        ]);

        $response = $this->postJson('/api/auth/forgot-password/request-otp', [
            'email' => 'test@example.com',
            'user_type' => 'pengguna'
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $this->assertDatabaseHas('password_reset_otps', [
            'email' => 'test@example.com',
            'user_type' => 'pengguna',
            'is_active' => true
        ]);

        Mail::assertQueued(SendOtpEmail::class);
    }

    public function test_can_verify_otp()
    {
        $user = Pengguna::factory()->create([
            'email_pengguna' => 'test@example.com'
        ]);

        $otp = PasswordResetOtp::create([
            'email' => 'test@example.com',
            'otp_code' => '123456',
            'user_type' => 'pengguna',
            'expires_at' => now()->addMinutes(2),
            'ip_address' => '127.0.0.1'
        ]);

        $response = $this->postJson('/api/auth/forgot-password/verify-otp', [
            'email' => 'test@example.com',
            'otp_code' => '123456',
            'user_type' => 'pengguna'
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => ['otp_token']
                ]);
    }

    public function test_can_reset_password()
    {
        $user = Pengguna::factory()->create([
            'email_pengguna' => 'test@example.com'
        ]);

        $otp = PasswordResetOtp::create([
            'email' => 'test@example.com',
            'otp_code' => '123456',
            'user_type' => 'pengguna',
            'expires_at' => now()->addMinutes(2),
            'ip_address' => '127.0.0.1'
        ]);

        $token = base64_encode('test@example.com|123456|pengguna|' . time());

        $response = $this->postJson('/api/auth/forgot-password/reset-password', [
            'otp_token' => $token,
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password_pengguna));
    }

    public function test_can_resend_otp()
    {
        Mail::fake();
        
        $user = Pengguna::factory()->create([
            'email_pengguna' => 'test@example.com'
        ]);

        $response = $this->postJson('/api/auth/forgot-password/resend-otp', [
            'email' => 'test@example.com',
            'user_type' => 'pengguna'
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $this->assertDatabaseHas('password_reset_otps', [
            'email' => 'test@example.com',
            'user_type' => 'pengguna',
            'is_active' => true
        ]);

        Mail::assertQueued(SendOtpEmail::class);
    }

    public function test_rate_limiting_works()
    {
        $user = Pengguna::factory()->create([
            'email_pengguna' => 'test@example.com'
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/forgot-password/request-otp', [
                'email' => 'test@example.com',
                'user_type' => 'pengguna'
            ])->assertStatus(200);
        }

        $response = $this->postJson('/api/auth/forgot-password/request-otp', [
            'email' => 'test@example.com',
            'user_type' => 'pengguna'
        ]);

        $response->assertStatus(429);
    }

    public function test_otp_email_command()
    {
        Mail::fake();
        
        $this->artisan('otp:test-email', ['email' => 'test@example.com'])
             ->expectsOutput('Test email sent successfully!')
             ->expectsOutput('SMTP connection successful!')
             ->assertExitCode(0);
        
        Mail::assertSent(Mailable::class, function ($mail) {
            return $mail->hasTo('test@example.com') &&
                   $mail->subject === 'Test OTP Email - INDOMAS';
        });
    }
}