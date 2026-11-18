<?php

namespace Tests\Feature;

use App\Models\MessageLog;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\TenantApproved;
use App\Services\Maps\GoogleMapsService;
use App\Services\Messaging\MailConfigurator;
use App\Services\Messaging\SmsService;
use App\Services\Messaging\WhatsAppService;
use App\Services\Push\FcmService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationsTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create();
    }

    private function save(string $group, array $data)
    {
        return $this->actingAs($this->admin)->put(route('admin.settings.update'), ['group' => $group] + $data);
    }

    public function test_every_settings_section_renders(): void
    {
        foreach (['billing', 'company', 'payments', 'email', 'sms', 'whatsapp', 'maps', 'push'] as $g) {
            $this->actingAs($this->admin)->get(route('admin.settings.edit', ['group' => $g]))->assertOk();
        }
        $this->actingAs($this->admin)->get(route('admin.messages.index'))->assertOk();
    }

    public function test_razorpay_settings_validation(): void
    {
        $this->save('payments', ['razorpay_mode' => 'test', 'razorpay_key_id' => 'rzp_live_ABC'])->assertSessionHasErrors(['razorpay_key_id', 'razorpay_key_secret']);
        $this->save('payments', ['razorpay_mode' => 'test', 'razorpay_key_id' => 'rzp_test_ABC', 'razorpay_key_secret' => 's3cret'])->assertSessionHasNoErrors();
        // Secret already stored → may be left blank on later saves.
        $this->save('payments', ['razorpay_mode' => 'test', 'razorpay_key_id' => 'rzp_test_ABC'])->assertSessionHasNoErrors();
        $this->save('payments', ['razorpay_mode' => 'disabled'])->assertSessionHasNoErrors();

        Http::fake(['api.razorpay.com/*' => Http::response(['items' => []])]);
        $this->save('payments', ['razorpay_mode' => 'test', 'razorpay_key_id' => 'rzp_test_ABC']);
        $this->actingAs($this->admin)->post(route('admin.settings.test', 'razorpay'))->assertSessionHas('success');
    }

    public function test_sms_msg91_and_fast2sms_and_logging(): void
    {
        $sms = app(SmsService::class);
        $this->assertSame('skipped', $sms->send('9876543210', 'hi')->status);

        $this->save('sms', ['sms_provider' => 'msg91', 'sms_template_id' => 'tmpl1'])->assertSessionHasErrors('sms_api_key');
        $this->save('sms', ['sms_provider' => 'msg91', 'sms_api_key' => 'authkey', 'sms_template_id' => 'tmpl1'])->assertSessionHasNoErrors();

        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'success', 'message' => 'req-1'])]);
        $log = $sms->send('+91 98765 43210', 'Your plan is active', null, 'test');
        $this->assertSame('sent', $log->status);
        Http::assertSent(fn ($r) => $r->hasHeader('authkey', 'authkey') && $r['recipients'][0]['mobiles'] === '919876543210' && $r['template_id'] === 'tmpl1');

        $this->assertSame('failed', $sms->send('12345', 'x')->status);

        PlatformSetting::setValue('sms_provider', 'fast2sms');
        Http::fake(['www.fast2sms.com/*' => Http::response(['return' => false, 'message' => ['Invalid Authentication']], 401)]);
        $failed = $sms->send('9876543210', 'hello');
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('Invalid Authentication', $failed->error);
    }

    public function test_whatsapp_template_send(): void
    {
        $this->save('whatsapp', ['whatsapp_enabled' => 1, 'whatsapp_phone_number_id' => '1234567890', 'whatsapp_access_token' => 'EAAG', 'whatsapp_api_version' => 'v21.0', 'whatsapp_template' => 'acx_notice', 'whatsapp_template_language' => 'en'])->assertSessionHasNoErrors();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

        $log = app(WhatsAppService::class)->notify('9876543210', 'Trip confirmed');
        $this->assertSame('sent', $log->status);
        $this->assertSame('wamid.1', $log->provider_message_id);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'v21.0/1234567890/messages') && $r['to'] === '919876543210'
            && $r['template']['name'] === 'acx_notice' && $r['template']['components'][0]['parameters'][0]['text'] === 'Trip confirmed');
    }

    public function test_notifications_use_sms_channel_when_configured(): void
    {
        PlatformSetting::setValue('sms_provider', 'log');
        $tenant = Tenant::factory()->pending()->create();
        $owner = $this->owner($tenant);
        $this->assertContains(SmsChannel::class, (new TenantApproved($tenant))->via($owner));

        $this->actingAs($this->admin)->post(route('admin.tenants.approve', $tenant));
        $this->assertTrue(MessageLog::where('channel', 'sms')->where('recipient', $owner->phone)->where('status', 'sent')->where('context', 'TenantApproved')->exists());
        $this->assertTrue(MessageLog::where('channel', 'mail')->where('recipient', $owner->email)->exists(), 'mail delivery logged');
    }

    public function test_mail_settings_applied_at_runtime_and_test_mail(): void
    {
        $this->save('email', ['mail_mailer' => 'smtp', 'mail_encryption' => 'ssl'])->assertSessionHasErrors(['mail_host', 'mail_port']);
        $this->save('email', ['mail_mailer' => 'smtp', 'mail_host' => 'smtp.hostinger.com', 'mail_port' => 465, 'mail_encryption' => 'ssl', 'mail_username' => 'noreply@aaochaletaxi.com', 'mail_password' => 'pw', 'mail_from_address' => 'noreply@aaochaletaxi.com', 'mail_from_name' => 'AaoChaleTaxi'])->assertSessionHasNoErrors();
        MailConfigurator::apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('pw', config('mail.mailers.smtp.password'));
        $this->assertSame('noreply@aaochaletaxi.com', config('mail.from.address'));

        PlatformSetting::setValue('mail_mailer', 'array');
        config(['mail.default' => 'array']);
        $this->actingAs($this->admin)->post(route('admin.settings.test', 'mail'), ['to' => 'ops@example.com'])->assertSessionHas('success');
        $this->assertTrue(MessageLog::where('channel', 'mail')->where('recipient', 'ops@example.com')->where('status', 'sent')->exists());
    }

    public function test_google_maps_distance(): void
    {
        $this->assertNull(app(GoogleMapsService::class)->distance('A', 'B'));
        PlatformSetting::setValue('google_maps_api_key', 'AIza', true);
        Http::fake(['maps.googleapis.com/*' => Http::response([
            'status' => 'OK', 'origin_addresses' => ['Mumbai'], 'destination_addresses' => ['Pune'],
            'rows' => [['elements' => [['status' => 'OK', 'distance' => ['value' => 148600], 'duration' => ['value' => 10500]]]]],
        ])]);
        $d = app(GoogleMapsService::class)->distance('Mumbai', 'Pune');
        $this->assertSame(148.6, $d['km']);
        $this->assertSame(175, $d['minutes']);
        $this->actingAs($this->admin)->post(route('admin.settings.test', 'maps'))->assertSessionHas('success');
    }

    public function test_fcm_push_with_service_account(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $sa = json_encode(['type' => 'service_account', 'client_email' => 'fcm@demo.iam.gserviceaccount.com', 'private_key' => $pem]);

        $this->save('push', ['fcm_project_id' => 'demo-app'])->assertSessionHasErrors('fcm_service_account');
        $this->save('push', ['fcm_project_id' => 'demo-app', 'fcm_service_account' => $sa])->assertSessionHasNoErrors();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.token']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/demo-app/messages/1']),
        ]);
        $log = app(FcmService::class)->send('device-token', 'Trip', 'Driver assigned');
        $this->assertSame('sent', $log->status);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'projects/demo-app/messages:send') && $r->hasHeader('Authorization', 'Bearer ya29.token'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com/token') && count(explode('.', $r['assertion'])) === 3);
    }

    public function test_test_endpoint_rejects_unknown_integration_and_non_admins(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.test', 'telegram'))->assertNotFound();
        $this->actingAs($this->owner())->post(route('admin.settings.test', 'sms'))->assertForbidden();
    }
}
