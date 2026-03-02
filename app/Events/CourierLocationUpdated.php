<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PRD §8.2 / §11 — Real-time courier location update broadcast.
 *
 * Channel: order.{orderId}  (public channel — customer subscribes)
 * Event name: CourierLocationUpdated
 *
 * Flutter listener:
 *   pusher.subscribe('order.$orderId')
 *     .bind('CourierLocationUpdated', (data) {
 *       updateMapMarker(data['lat'], data['lng']);
 *     });
 */
class CourierLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int   $orderId,
        public readonly float $lat,
        public readonly float $lng,
    ) {}

    public function broadcastOn(): Channel
    {
        // Public channel — customer does not need auth to subscribe
        return new Channel('order.' . $this->orderId);
    }

    public function broadcastAs(): string
    {
        return 'CourierLocationUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'lat'      => $this->lat,
            'lng'      => $this->lng,
        ];
    }
}
