<?php

namespace Tests\Feature;

use App\Mail\BrandedMail;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantApproved;
use App\Services\Messaging\OtpService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    public function test_branded_mail_uses_theme_and_footer(): void
    {
        $html = (new BrandedMail(subjectLine: 'S', heading: 'Verify your mobile number', lines: ['Hello <b>x</b>'], details: ['Booking' => 'BK1'],
            actionText: 'Track your booking', actionUrl: 'https://example.com/b/1', code: '482913'))->render();

        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString('background-color: #ffb703', $html);   // inlined brand theme (button / header bar)
        $this->assertStringContainsString('class="code-box"', $html);
        $this->assertStringContainsString('Track your booking', $html);
        $this->assertStringContainsString(config('aaochale.support_email'), $html);
        $this->assertStringContainsString(route('legal.privacy'), $html);
        $this->assertStringContainsString('Team AaoChaleTaxi', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);                  // user text is escaped
    }

    public function test_notifications_use_the_same_layout(): void
    {
        $html = (string) (new TenantApproved(new Tenant(['name' => 'Shiv Travels'])))->toMail(new User(['name' => 'Ramesh', 'email' => 'r@example.com']))->render();

        $this->assertStringContainsString('Congratulations Ramesh!', $html);
        $this->assertStringContainsString('AaoChale', $html);
        $this->assertStringContainsString('Team AaoChaleTaxi', $html);
        $this->assertStringNotContainsString('Reply to this email', $html);
    }

    public function test_otp_by_email_sends_branded_mail(): void
    {
        Mail::fake();
        $this->assertSame('email', app(OtpService::class)->send('9825012345', 'priya@example.com'));
        Mail::assertSent(BrandedMail::class, fn (BrandedMail $m) => $m->hasTo('priya@example.com') && $m->code === OtpService::$lastCode
            && str_contains($m->subjectLine, OtpService::$lastCode));
    }
}
