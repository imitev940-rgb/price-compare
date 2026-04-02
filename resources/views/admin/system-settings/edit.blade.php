@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:1000px;">
    <div style="margin-bottom:20px;">
        <h1 style="font-size:28px;font-weight:700;">System Settings</h1>
        <p style="color:#6b7280;">Configure system-wide settings.</p>
    </div>

    @if(session('success'))
        <div style="margin-bottom:16px;padding:12px;border-radius:12px;background:#dcfce7;color:#166534;">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div style="margin-bottom:16px;padding:12px;border-radius:12px;background:#fee2e2;color:#991b1b;">
            <ul style="margin-left:16px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="background:#fff;padding:24px;border-radius:16px;border:1px solid #e5e7eb;">
        <form method="POST" action="{{ route('admin.system-settings.update') }}">
            @csrf
            @method('PUT')

            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">

                <div>
                    <label>System Name</label>
                    <input type="text" name="system_name" value="{{ $settings['system_name'] }}"
                           style="width:100%;padding:10px;border-radius:10px;border:1px solid #ccc;">
                </div>

                <div>
                    <label>Default Language</label>
                    <input type="text" name="default_language" value="{{ $settings['default_language'] }}"
                           style="width:100%;padding:10px;border-radius:10px;border:1px solid #ccc;">
                </div>

                <div>
                    <label>Timezone</label>
                    <input type="text" name="timezone" value="{{ $settings['timezone'] }}"
                           style="width:100%;padding:10px;border-radius:10px;border:1px solid #ccc;">
                </div>

                <div>
                    <label>Currency</label>
                    <input type="text" name="currency" value="{{ $settings['currency'] }}"
                           style="width:100%;padding:10px;border-radius:10px;border:1px solid #ccc;">
                </div>

                <div>
                    <label>Notification Refresh (seconds)</label>
                    <input type="number" name="notification_refresh_interval" value="{{ $settings['notification_refresh_interval'] }}"
                           style="width:100%;padding:10px;border-radius:10px;border:1px solid #ccc;">
                </div>

                <div>
                    <label>Version</label>
                    <input type="text" name="footer_version" value="{{ $settings['footer_version'] }}"
                           style="width:100%;padding:10px;border-radius:10px;border:1px solid #ccc;">
                </div>

                <div style="grid-column:span 2;">
                    <label>Created By</label>
                    <input type="text" name="created_by" value="{{ $settings['created_by'] }}"
                           style="width:100%;padding:10px;border-radius:10px;border:1px solid #ccc;">
                </div>

            </div>

            <div style="margin-top:20px;">
                <button type="submit"
                        style="padding:12px 20px;border:none;border-radius:12px;background:#2563eb;color:#fff;font-weight:700;">
                    Save Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection