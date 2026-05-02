@extends('layouts.app')

@section('content')
<div style="padding: 24px; max-width: 1400px; margin: 0 auto;">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h1 style="font-size:28px; font-weight:700; color:#111827; margin:0 0 6px;">История на промените</h1>
            <p style="font-size:14px; color:#6b7280; margin:0;">Всички Save All събития</p>
        </div>
        <a href="{{ route('priceControlIndex') }}" style="padding:10px 16px; background:#185fa5; color:white; text-decoration:none; border-radius:8px; font-weight:600; font-size:14px;">← Към Price Control</a>
    </div>

    @if($snapshots->isEmpty())
        <div style="background:white; padding:48px 24px; border-radius:12px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
            <p style="font-size:16px; color:#6b7280; margin:0;">Все още няма запазени промени.</p>
            <a href="{{ route('priceControlIndex') }}" style="display:inline-block; margin-top:16px; padding:10px 18px; background:#185fa5; color:white; text-decoration:none; border-radius:8px; font-weight:600;">Започни редакция</a>
        </div>
    @else
        <div style="background:white; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.08); overflow:hidden;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                        <th style="padding:14px 16px; text-align:left; font-weight:600; color:#374151; font-size:13px;">Дата</th>
                        <th style="padding:14px 16px; text-align:left; font-weight:600; color:#374151; font-size:13px;">Потребител</th>
                        <th style="padding:14px 16px; text-align:right; font-weight:600; color:#374151; font-size:13px; width:120px;">Промени</th>
                        <th style="padding:14px 16px; text-align:right; font-weight:600; color:#374151; font-size:13px; width:140px;">Действие</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($snapshots as $snapshot)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:14px 16px;">
                            <div style="font-weight:500; color:#111827;">{{ $snapshot->created_at->format('d.m.Y') }}</div>
                            <div style="font-size:12px; color:#9ca3af; margin-top:2px;">{{ $snapshot->created_at->format('H:i') }}</div>
                        </td>
                        <td style="padding:14px 16px; color:#374151;">
                            {{ $snapshot->user ? $snapshot->user->name : 'Неизвестен' }}
                        </td>
                        <td style="padding:14px 16px; text-align:right; font-weight:600; color:#185fa5;">
                            {{ $snapshot->product_count }} продукта
                        </td>
                        <td style="padding:14px 16px; text-align:right;">
                            <a href="{{ route('priceControlDownload', $snapshot->id) }}" style="display:inline-block; padding:8px 14px; background:#185fa5; color:white; text-decoration:none; border-radius:6px; font-weight:600; font-size:13px;">⬇ Excel</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:20px;">
            {{ $snapshots->links() }}
        </div>
    @endif
</div>
@endsection
