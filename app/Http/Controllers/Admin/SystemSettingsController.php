<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function edit(): View
    {
        $settings = [
            'system_name' => Setting::getValue('system_name', 'PriceHunterPro'),
            'default_language' => Setting::getValue('default_language', 'en'),
            'timezone' => Setting::getValue('timezone', 'Europe/Sofia'),
            'currency' => Setting::getValue('currency', 'EUR'),
            'notification_refresh_interval' => Setting::getValue('notification_refresh_interval', '10'),
            'footer_version' => Setting::getValue('footer_version', '8.0'),
            'created_by' => Setting::getValue('created_by', 'SITEZZY – Ivan Mitev'),
        ];

        return view('admin.system-settings.edit', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'system_name' => ['required', 'string', 'max:255'],
            'default_language' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'max:10'],
            'notification_refresh_interval' => ['required', 'integer', 'min:5', 'max:3600'],
            'footer_version' => ['required', 'string', 'max:50'],
            'created_by' => ['required', 'string', 'max:255'],
        ]);

        foreach ($data as $key => $value) {
            Setting::setValue($key, $value);
        }

        return redirect()
            ->route('admin.system-settings.edit')
            ->with('success', 'System settings updated successfully.');
    }
}