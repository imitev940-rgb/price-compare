<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class PriceCheckController extends Controller
{
    public function run(Request $request)
    {
        Artisan::call('prices:check');

        return redirect()->route('comparison')
            ->with('success', 'Цените бяха обновени успешно.');
    }
}