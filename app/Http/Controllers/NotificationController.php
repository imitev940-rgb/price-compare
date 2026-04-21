<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $notifications = Notification::with(['product', 'store'])
            ->whereNotIn('id', function ($q) use ($userId) {
                $q->select('notification_id')->from('notification_reads')->where('user_id', $userId);
            })
            ->latest()
            ->take(20)
            ->get();

        $unreadCount = Notification::whereNotIn('id', function ($q) use ($userId) {
            $q->select('notification_id')->from('notification_reads')->where('user_id', $userId);
        })->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    public function clearAll(): JsonResponse
    {
        $userId = Auth::id();

        $ids = Notification::whereNotIn('id', function ($q) use ($userId) {
            $q->select('notification_id')->from('notification_reads')->where('user_id', $userId);
        })->pluck('id');

        $rows = $ids->map(fn ($id) => [
            'user_id'         => $userId,
            'notification_id' => $id,
            'read_at'         => now(),
        ])->toArray();

        if (!empty($rows)) {
            DB::table('notification_reads')->insertOrIgnore($rows);
        }

        return response()->json(['success' => true]);
    }

    public function showAll(Request $request)
    {
        $userId = Auth::id();

        $query = Notification::with(['product', 'store'])
            ->whereNotIn('notifications.id', function ($q) use ($userId) {
                $q->select('notification_id')->from('notification_reads')->where('user_id', $userId);
            });

        // Филтри
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->filled('period')) {
            match($request->period) {
                'today' => $query->where('created_at', '>=', now()->startOfDay()),
                'week'  => $query->where('created_at', '>=', now()->subDays(7)),
                'month' => $query->where('created_at', '>=', now()->subDays(30)),
                default => null,
            };
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', fn($q) => $q->where('name', 'like', "%$search%"));
        }

        $all = $query->latest()->get();

        // Статистики
        $cheaper = $all->where('price_change_percent', '<', 0)->count();
        $pricier = $all->where('price_change_percent', '>', 0)->count();

        $byStore = $all->groupBy(fn($n) => $n->store?->name ?? 'Неопределен')
            ->map(function ($items) {
                $percents = $items->pluck('price_change_percent')->filter(fn($p) => $p !== null);
                return [
                    'count'    => $items->count(),
                    'avg'      => $percents->count() ? round($percents->avg(), 2) : null,
                    'cheaper'  => $items->where('price_change_percent', '<', 0)->count(),
                    'pricier'  => $items->where('price_change_percent', '>', 0)->count(),
                ];
            });

        // Top 5 най-голяма промяна
        $topChanges = $all->filter(fn($n) => $n->price_change_percent !== null)
            ->sortByDesc(fn($n) => abs($n->price_change_percent))
            ->take(5)
            ->values();

        // По дни (последни 7 дни)
        $byDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $byDay[$day->format('d.m')] = $all->filter(fn($n) => $n->created_at->isSameDay($day))->count();
        }

        $summary = [
            'total'      => $all->count(),
            'cheaper'    => $cheaper,
            'pricier'    => $pricier,
            'last_24h'   => $all->where('created_at', '>=', now()->subDay())->count(),
            'last_7d'    => $all->where('created_at', '>=', now()->subDays(7))->count(),
            'by_store'   => $byStore,
            'top'        => $topChanges,
            'by_day'     => $byDay,
        ];

        $stores = \App\Models\Store::orderBy('name')->get(['id','name']);

        return view('notifications.index', [
            'notifications' => $all,
            'summary'       => $summary,
            'stores'        => $stores,
            'filters'       => $request->only(['store_id','period','search']),
        ]);
    }
}
