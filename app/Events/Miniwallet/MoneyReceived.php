<?php

namespace App\Events\Miniwallet;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MoneyReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bcastData; 
    public $ref_number;
    public $receiverId;
    public $senderId;
    public $amount;
    public $newBalance; 

    public function __construct($bcastData)
    {
        $this->ref_number = $bcastData['ref_number'];
        $this->receiverId = $bcastData['receiver_id'];
        $this->senderId = $bcastData['sender_id'];
        $this->amount = $bcastData['amount'];
        $this->newBalance = $bcastData['new_balance'];
    }

    public function broadcastOn(): array
    { 

        return [
            new Channel('user.' . $this->receiverId),
            new PrivateChannel('user.' . $this->receiverId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'money.received';
    }

    public function broadcastWith(): array
    {
        $withData = [
            'ref_number' => $this->ref_number,
            'receiver_id' => $this->receiverId,
            'sender_id' => $this->senderId,
            'amount' => number_format($this->amount, 2),
            'new_balance' => number_format($this->newBalance, 2),
        ];

        return $withData;
    }
}