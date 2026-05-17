<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LockReleased implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $documentId;
    public string $userId;

    public function __construct(string $documentId, string $userId)
    {
        $this->documentId = $documentId;
        $this->userId = $userId;
    }

    public function broadcastOn()
    {
        return new Channel('document.' . $this->documentId);
    }
}