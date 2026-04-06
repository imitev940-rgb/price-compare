<?php

namespace App\Http\Controllers;

use App\Jobs\PriceCheckLinkJob;
use App\Models\CompetitorLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class PriceCheckController extends Controller
{
    // ================================================================
    // Пусни проверка за ЕДИН линк (AJAX бутон от UI)
    // ================================================================

    public function runLink(Request $request, CompetitorLink $link)
    {
        dispatch(new PriceCheckLinkJob($link->id));

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Проверката е пусната на опашката.']);
        }

        return back()->with('success', 'Проверката е пусната за линк #' . $link->id . '.');
    }

    // ================================================================
    // Пусни проверка за ЕДИН продукт (всичките му линкове)
    // ================================================================

    public function runProduct(Request $request, int $productId)
    {
        $links = CompetitorLink::where('product_id', $productId)
            ->where('is_active', 1)
            ->get();

        if ($links->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Няма активни линкове за този продукт.'], 404);
            }

            return back()->with('error', 'Няма активни линкове за този продукт.');
        }

        foreach ($links as $link) {
            dispatch(new PriceCheckLinkJob($link->id));
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Пуснати ' . $links->count() . ' проверки на опашката.']);
        }

        return back()->with('success', 'Пуснати ' . $links->count() . ' проверки за продукт #' . $productId . '.');
    }

    // ================================================================
    // Пусни проверка за ВСИЧКИ (само от scheduler/admin)
    // ================================================================

    public function runAll(Request $request)
    {
        try {
            // Dispatch-ва jobs на опашката — не блокира request-а
            Artisan::queue('prices:dispatch-due', [
                '--force' => true,
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Проверката на всички цени е пусната.']);
            }

            return back()->with('success', 'Проверката на всички цени е пусната на опашката.');

        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Грешка: ' . $e->getMessage()], 500);
            }

            return back()->with('error', 'Грешка: ' . $e->getMessage());
        }
    }
}