<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AuditController extends Controller
{
    /**
     * GET /api/admin/logs
     * Paginated activity logs across the system.
     */
    public function logs()
    {
        $logs = ActivityLog::with('user')
            ->latest()
            ->paginate(50);

        return response()->json($logs);
    }

    /**
     * GET /api/admin/login-attempts
     * Last 200 login-related activity logs.
     */
    public function loginAttempts()
    {
        $attempts = ActivityLog::where('action', 'like', '%login%')
            ->with('user')
            ->latest()
            ->limit(200)
            ->get();

        return response()->json($attempts);
    }

    /**
     * GET /api/admin/system-health
     * Returns basic system health metrics.
     */
    public function systemHealth()
    {
        // DB connectivity check
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            $dbStatus = 'disconnected';
        }

        // Queue status (checks jobs table)
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs  = DB::table('failed_jobs')->count();

        return response()->json([
            'database'     => $dbStatus,
            'pending_jobs' => $pendingJobs,
            'failed_jobs'  => $failedJobs,
            'timestamp'    => now()->toIso8601String(),
        ]);
    }
}
