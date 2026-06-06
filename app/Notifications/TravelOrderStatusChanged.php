<?php

namespace App\Notifications;

use App\Models\TravelOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TravelOrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TravelOrder $travelOrder)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Pedido de viagem {$this->travelOrder->status->value}")
            ->greeting("Ola, {$notifiable->name}.")
            ->line("Seu pedido de viagem para {$this->travelOrder->destination} foi {$this->travelOrder->status->value}.")
            ->line("Ida: {$this->travelOrder->departure_date->toDateString()}.")
            ->line("Volta: {$this->travelOrder->return_date->toDateString()}.");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'travel_order_id' => $this->travelOrder->id,
            'destination' => $this->travelOrder->destination,
            'departure_date' => $this->travelOrder->departure_date->toDateString(),
            'return_date' => $this->travelOrder->return_date->toDateString(),
            'status' => $this->travelOrder->status->value,
        ];
    }
}
