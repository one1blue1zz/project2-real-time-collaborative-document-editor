<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentContentUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $document;
    public string $content;
    public string $userId;
    public string $userName;
    public int $version;

    public function __construct(array $document, string $content, string $userId, string $userName, int $version)
    {
        $this->document = $document;
        $this->content = $content;
        $this->userId = $userId;
        $this->userName = $userName;
        $this->version = $version;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('document.' . $this->document['id']);
    }

    public function broadcastAs(): string
    {
        return 'content.update';
    }
}