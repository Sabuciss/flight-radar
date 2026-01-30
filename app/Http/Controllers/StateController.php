<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class StateController extends Controller
{
    public function index(Request $request)
    {
        // Cache flight data priekš 30 sekundēm lai samazinātu API izsaukumu skaitu
        $cacheKey = 'opensky_states';
        $cacheDuration = 30; // sekundes

        $data = Cache::remember($cacheKey, $cacheDuration, function () {
            try {
                $response = Http::timeout(15)->get('https://opensky-network.org/api/states/all');

                if (!$response->successful()) {
                    return ['error' => 'Unable to fetch OpenSky data', 'status' => $response->status()];
                }

                return $response->json();
            } catch (\Exception $e) {
                return ['error' => 'API request failed: ' . $e->getMessage()];
            }
        });

        if (isset($data['error'])) {
            return response()->json($data, $data['status'] ?? 502);
        }

        return response()->json($data);
    }
}
