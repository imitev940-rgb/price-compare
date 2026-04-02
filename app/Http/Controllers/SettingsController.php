<?php

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        $settings = $user->setting()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'language' => 'bg',
                'theme' => 'light',
                'notifications_enabled' => true,
                'refresh_interval' => 60,
            ]
        );

        return view('settings.edit', compact('user', 'settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'language' => ['required', 'in:bg,en,de,fr,es,ro,tr'],
            'theme' => ['required', 'in:light,dark'],
            'refresh_interval' => ['required', 'in:30,60,120,300'],
            'notifications_enabled' => ['nullable', 'boolean'],
        ]);

        $settings = $user->setting()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'language' => 'bg',
                'theme' => 'light',
                'notifications_enabled' => true,
                'refresh_interval' => 60,
            ]
        );

        $settings->update([
            'language' => $data['language'],
            'theme' => $data['theme'],
            'refresh_interval' => (int) $data['refresh_interval'],
            'notifications_enabled' => $request->boolean('notifications_enabled'),
        ]);

        return redirect()
            ->route('settings.edit')
            ->with('success', 'Settings updated successfully.');
    }
}