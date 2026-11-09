<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SaludController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->select('SELECT 1');

            return response()->json(['estado' => 'ok'])->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            Log::channel('seguridad')->warning('salud_no_disponible');

            return response()->json(['estado' => 'no_disponible'], 503)->header('Cache-Control', 'no-store');
        }
    }
}
