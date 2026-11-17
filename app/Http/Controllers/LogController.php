<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogController extends Controller
{
    public function readAll(Request $request)
    {
        try {
            // Check whether the logged-in user is an admin
            $role = DB::table('roles')
                ->where('id', $request->user()->role_id)
                ->value('name');

            if ($role != 'ADMINISTRATOR') {
                return response()->json([
                    'message' => 'Access denied.'
                ], 403);
            }

            $logs = Log::latest()->get();

            return response()->json([
                'logs' => $logs
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch system logs.'
            ], 500);
        }
    }
}
