<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderDispatchLog;
use App\Services\DispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AssignOrderTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    protected $courierId;

    public function __construct(Order $order, int $courierId)
    {
        $this->order = $order;
        $this->courierId = $courierId;
    }

    public function handle(DispatchService $dispatchService)
    {
        // 1. Check if the courier has reacted to the order
        $log = OrderDispatchLog::where('order_id', $this->order->id)
            ->where('courier_id', $this->courierId)
            ->first();

        if ($log && $log->status === 'pending') {
            // 2. Mark as timeout
            $log->update(['status' => 'timeout', 'responded_at' => now()]);
            
            Log::info("Timeout for Order #{$this->order->id} and Courier #{$this->courierId}. Moving to next courier.");

            // 3. Trigger next assignment in Failover Mechanism
            $dispatchService->dispatchOrder($this->order);
        }
    }
}
