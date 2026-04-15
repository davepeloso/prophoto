<?php

namespace ProPhoto\Notifications\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Story 7.5 — Email sent to each client when a gallery is marked as delivered.
 *
 * One email per active share — so every client who has access gets notified.
 * Includes the photographer's optional delivery message and a link back to
 * the gallery viewer.
 */
class GalleryDeliveredMail extends Mailable
{
    use Queueable, SerializesModels;

    public string  $galleryName;
    public ?string $deliveryMessage;
    public string  $viewerUrl;
    public string  $deliveredAt;

    public function __construct(
        string  $galleryName,
        ?string $deliveryMessage,
        string  $viewerUrl,
        string  $deliveredAt,
    ) {
        $this->galleryName     = $galleryName;
        $this->deliveryMessage = $deliveryMessage;
        $this->viewerUrl       = $viewerUrl;
        $this->deliveredAt     = $deliveredAt;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Gallery is Ready: {$this->galleryName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'prophoto-notifications::emails.gallery-delivered',
        );
    }
}
