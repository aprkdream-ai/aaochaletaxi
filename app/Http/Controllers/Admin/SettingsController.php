<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Services\AuditLogger;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $group = $request->query('group', 'billing');
        abort_unless(array_key_exists($group, PlatformSettings::groups()), 404);

        $values = [];
        foreach (PlatformSettings::fields($group) as $key => $field) {
            $values[$key] = $field['type'] === 'secret' ? null : PlatformSetting::getValue($key);
        }
        $secretsSet = PlatformSetting::query()->whereIn('key', array_keys(array_filter(PlatformSettings::fields($group), fn ($f) => $f['type'] === 'secret')))
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

        $data = $request->validate(collect($fields)->mapWithKeys(fn ($f, $k) => [$k => $f['rules']])->all());
        $changed = [];

        foreach ($fields as $key => $field) {
            $value = $data[$key] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            if ($field['type'] === 'secret') {
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
}
