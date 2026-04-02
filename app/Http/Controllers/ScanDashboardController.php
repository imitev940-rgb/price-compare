<?php

namespace App\Http\Controllers;

use App\Models\CompetitorLink;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ScanDashboardController extends Controller
{
    public function index(Request $request)
    {
        $now = now();

        $links = CompetitorLink::query()
            ->with(['product:id,name,sku,ean,brand,scan_priority,is_active', 'store:id,name'])
            ->where('is_active', 1)
            ->whereHas('product', function ($q) {
                $q->where('is_active', 1);
            })
            ->get();

        $activeLinks = $links->count();

        $dueLinks = $links->filter(function ($link) use ($now) {
            $product = $link->product;
            $storeName = strtolower(trim((string) ($link->store->name ?? '')));

            if (!$product || $storeName === '') {
                return false;
            }

            $hours = $this->resolveHours((string) ($product->scan_priority ?? 'normal'), $storeName);

            if ($hours === null) {
                return false;
            }

            return $link->last_checked_at === null
                || $link->last_checked_at->copy()->addHours($hours)->lte($now);
        })->values();

        // 🔍 SEARCH
        $search = trim((string) $request->get('q', ''));

        if ($search !== '') {
            $searchLower = mb_strtolower($search);

            $dueLinks = $dueLinks->filter(function ($link) use ($searchLower) {
                $product = $link->product;

                if (!$product) return false;

                return str_contains(mb_strtolower((string) ($product->name ?? '')), $searchLower)
                    || str_contains(mb_strtolower((string) ($product->sku ?? '')), $searchLower)
                    || str_contains(mb_strtolower((string) ($product->ean ?? '')), $searchLower)
                    || str_contains(mb_strtolower((string) ($product->brand ?? '')), $searchLower);
            })->values();
        }

        // 🎯 PRIORITY FILTER
        $priority = trim((string) $request->get('priority', ''));

        if ($priority !== '') {
            $dueLinks = $dueLinks->filter(function ($link) use ($priority) {
                $scanPriority = (string) ($link->product->scan_priority ?? 'normal');

                if ($priority === 'top') return $scanPriority === 'top';
                if ($priority === 'normal') return $scanPriority !== 'top';

                return true;
            })->values();
        }

        $dueTop = $dueLinks->filter(fn($l) => ($l->product->scan_priority ?? 'normal') === 'top')->count();
        $dueNormal = $dueLinks->filter(fn($l) => ($l->product->scan_priority ?? 'normal') !== 'top')->count();

        $checkedToday = $links->filter(fn($l) => $l->last_checked_at && $l->last_checked_at->isToday())->count();
        $blockedCount = $links->where('search_status', 'blocked')->count();

        $errorCount = $links->filter(fn($l) =>
            in_array($l->search_status, ['error','request_failed','price_not_found','mismatch','invalid_url'], true)
        )->count();

        $latestCheckedAt = $links->pluck('last_checked_at')->filter()->sortDesc()->first();

        // 🧠 SORT
        $sorted = $dueLinks->sortBy(function ($link) use ($now) {
            if ($link->last_checked_at === null) return -999999;

            $product = $link->product;
            $storeName = strtolower(trim((string) ($link->store->name ?? '')));
            $hours = $this->resolveHours((string) ($product->scan_priority ?? 'normal'), $storeName);

            if ($hours === null) return 999999;

            return $link->last_checked_at->copy()->addHours($hours)->diffInMinutes($now, false);
        })->values();

        // 📄 PAGINATION
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;

        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        $dueTable = new LengthAwarePaginator(
            $items,
            $sorted->count(),
            $perPage,
            $page,
            [
                'path' => url()->current(),
                'query' => $request->query(),
            ]
        );

        return view('scan-dashboard.index', compact(
            'activeLinks',
            'dueTop',
            'dueNormal',
            'checkedToday',
            'blockedCount',
            'errorCount',
            'latestCheckedAt',
            'dueTable'
        ) + [
            'dueCount' => $dueLinks->count(),
        ]);
    }

    private function resolveHours(string $priority, string $storeName): ?int
    {
        if ($priority === 'top') return 1;

        return match ($storeName) {
            'pazaruvaj' => 3,
            'technopolis' => 6,
            'technomarket' => 12,
            'techmart', 'tehnomix' => 24,
            default => 24,
        };
    }
}