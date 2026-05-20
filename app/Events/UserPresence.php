<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPresence implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $documentId;
    public string $userId;
    public string $userName;
    public string $action;

    public function __construct(string $documentId, string $userId, string $userName, string $action)
    {
        $this->documentId = $documentId;
        $this->userId = $userId;
        $this->userName = $userName;
        $this->action = $action;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('document.' . $this->documentId);
    }

    public function broadcastAs(): string
    {
        return 'user.presence';
    }
}