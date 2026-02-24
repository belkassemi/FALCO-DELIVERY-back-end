<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class LogService
{
    /**
     * Log a sensitive activity.
     */
    public function log(string $action, $target = null, array $data = [])
    {
        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => $action,
            'target_type' => $target ? get_class($target) : null,
            'target_id'   => $target ? $target->id : null,
            'data'        => $data,
        ]);
    }
}
