<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $oldStatus,
        public string $newStatus,
    ) {}

    public function envelope(): Envelope
    {
        $statusLabel = $this->statusLabel($this->newStatus);

        return new Envelope(
            subject: "Pedido #{$this->order->id} — {$statusLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-status-changed',
            with: [
                'order'      => $this->order,
                'oldStatus'  => $this->oldStatus,
                'newStatus'  => $this->newStatus,
                'newLabel'   => $this->statusLabel($this->newStatus),
                'oldLabel'   => $this->statusLabel($this->oldStatus),
            ],
        );
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending'   => 'Pendiente',
            'confirmed' => 'Confirmado',
            'preparing' => 'Preparando',
            'shipped'   => 'En camino',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado',
            'modified'  => 'Modificado',
            default     => $status,
        };
    }
}
