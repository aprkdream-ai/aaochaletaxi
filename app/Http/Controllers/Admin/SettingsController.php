<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BrandedMail;
use App\Models\MessageLog;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Services\AuditLogger;
use App\Services\Maps\GoogleMapsService;
use App\Services\Maps\PlaceSuggestService;
use App\Services\Messaging\SmsService;
use App\Services\Messaging\WhatsAppService;
use App\Services\Payments\RazorpayGateway;
use App\Services\Push\FcmService;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $group = $request->query('group', 'billing');
        abort_unless(array_key_exists($group, PlatformSettings::groups()), 404);

        $values = [];
        foreach (PlatformSettings::fields($group) as $key => $field) {
            $values[$key] = PlatformSettings::isSecret($field) ? null : PlatformSetting::getValue($key, $field['default'] ?? null);
        }
        $secretsSet = PlatformSetting::query()->whereIn('key', array_keys(array_filter(PlatformSettings::fields($group), fn ($f) => PlatformSettings::isSecret($f))))
            ->whereNotNull('value')->pluck('key')->all();

        return view('admin.settings.edit', [
            'groups' => PlatformSettings::groups(),
            'group' => $group,
            'values' => $values,
            'secretsSet' => $secretsSet,
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->get(['code', 'name']),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $group = $request->input('group');
        abort_unless(is_string($group) && array_key_exists($group, PlatformSettings::groups()), 404);
        $fields = PlatformSettings::fields($group);

        foreach ($fields as $key => $field) {
            if ($field['type'] === 'toggle') {
                $request->merge([$key => $request->boolean($key) ? '1' : '0']);
            }
        }
        $data = $request->validate(collect($fields)->mapWithKeys(fn ($f, $k) => [$k => $f['rules']])->all());
        $this->assertRequiredSecrets($request, $group, $data);
        $changed = [];

        foreach ($fields as $key => $field) {
            $value = $data[$key] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            if (PlatformSettings::isSecret($field)) {
                if ($request->boolean("clear_$key")) {
                    PlatformSetting::setValue($key, null, true);
                    $changed[] = $key;
                } elseif (filled($value)) { // blank keeps the existing secret
                    PlatformSetting::setValue($key, (string) $value, true);
                    $changed[] = $key;
                }

                continue;
            }
            $value = $value === null || $value === '' ? null : (string) $value;
            if (in_array($key, ['platform_gstin', 'platform_pan'], true) && $value) {
                $value = strtoupper($value);
            }
            if (PlatformSetting::getValue($key) !== $value) {
                PlatformSetting::setValue($key, $value);
                $changed[] = $key;
            }
        }

        // Never log secret values — only which keys changed.
        $audit->log('platform.settings_updated', null, [], ['group' => $group, 'keys' => $changed]);

        return redirect()->route('admin.settings.edit', ['group' => $group])->with('success', __('Settings saved.'));
    }

    /** Secrets are write-only, so "required" means: submitted now OR already stored. */
    private function assertRequiredSecrets(Request $request, string $group, array $data): void
    {
        $needs = match ($group) {
            'payments' => ($data['razorpay_mode'] ?? 'disabled') !== 'disabled' ? ['razorpay_key_secret'] : [],
            'sms' => in_array($data['sms_provider'] ?? 'none', ['msg91', 'fast2sms'], true) ? ['sms_api_key'] : [],
            'whatsapp' => ($data['whatsapp_enabled'] ?? '0') === '1' ? ['whatsapp_access_token'] : [],
            'push' => filled($data['fcm_project_id'] ?? null) ? ['fcm_service_account'] : [],
            default => [],
        };
        $errors = [];
        foreach ($needs as $key) {
            $stored = filled(PlatformSetting::getValue($key)) && ! $request->boolean("clear_$key");
            if (! $stored && blank($request->input($key))) {
                $errors[$key] = __('This secret is required for the selected option.');
            }
        }
        if ($group === 'payments' && ($mode = $data['razorpay_mode'] ?? 'disabled') !== 'disabled' && ! str_starts_with((string) ($data['razorpay_key_id'] ?? ''), "rzp_{$mode}_")) {
            $errors['razorpay_key_id'] = __('Use an rzp_:mode_ key for :mode mode.', ['mode' => $mode]);
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array{ok:bool, message:string} */
    private function testMaps(): array
    {
        $dm = app(GoogleMapsService::class)->test();
        if (! $dm['ok'] || ! app(GoogleMapsService::class)->googleEnabled()) {
            return $dm;
        }
        $ac = app(PlaceSuggestService::class)->test();

        return ['ok' => true, 'message' => $dm['message'].' '.$ac['message']]; // Places is optional (city list fallback)
    }

    /** "Send test" for an integration; results are shown as a flash message and logged. */
    public function test(Request $request, string $integration): RedirectResponse
    {
        $groupFor = ['razorpay' => 'payments', 'mail' => 'email', 'sms' => 'sms', 'whatsapp' => 'whatsapp', 'maps' => 'maps', 'push' => 'push'];
        abort_unless(isset($groupFor[$integration]), 404);
        $to = trim((string) $request->input('to'));

        $result = match ($integration) {
            'razorpay' => app(RazorpayGateway::class)->test(),
            'maps' => $this->testMaps(),
            'push' => app(FcmService::class)->test($to ?: null),
            'mail' => $this->testMail($request, $to),
            'sms' => $this->fromLog(app(SmsService::class)->send($to, __('AaoChaleTaxi test SMS. If you received this, SMS is working.'), null, 'admin.test')),
            'whatsapp' => $this->fromLog(app(WhatsAppService::class)->canNotify()
                ? app(WhatsAppService::class)->notify($to, __('AaoChaleTaxi test message. WhatsApp is working.'), null, 'admin.test')
                : app(WhatsAppService::class)->sendTemplate($to, 'hello_world', [], null, 'admin.test')),
        };

        app(AuditLogger::class)->log('platform.integration_tested', null, [], ['integration' => $integration, 'ok' => $result['ok']]);

        return redirect()->route('admin.settings.edit', ['group' => $groupFor[$integration]])
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function testMail(Request $request, string $to): array
    {
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => __('Enter a valid email address.')];
        }
        try {
            Mail::to($to)->send(new BrandedMail(
                subjectLine: __('AaoChaleTaxi test email'),
                heading: __('Email delivery is working'),
                lines: [__('This is a test email from AaoChaleTaxi. If you can read it, your mail settings are correct.')],
                details: [__('Mailer') => (string) config('mail.default'), __('Sent at') => now()->format('d M Y, h:i A')],
            ));

            return ['ok' => true, 'message' => __('Test email sent to :to via :m.', ['to' => $to, 'm' => config('mail.default')])];
        } catch (\Throwable $e) {
            MessageLog::record(['channel' => 'mail', 'provider' => config('mail.default'), 'recipient' => $to, 'subject' => 'test', 'status' => 'failed', 'error' => $e->getMessage(), 'context' => 'admin.test']);

            return ['ok' => false, 'message' => __('Email failed: :e', ['e' => Str::limit($e->getMessage(), 200)])];
        }
    }

    private function fromLog(MessageLog $log): array
    {
        return match ($log->status) {
            'sent' => ['ok' => true, 'message' => __('Sent to :to (id :id).', ['to' => $log->recipient, 'id' => $log->provider_message_id])],
            'skipped' => ['ok' => false, 'message' => __('Not sent: :e', ['e' => $log->error])],
            default => ['ok' => false, 'message' => __('Failed: :e', ['e' => Str::limit((string) $log->error, 200)])],
        };
    }
}
