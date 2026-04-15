<?php

namespace ProPhoto\Notifications\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use ProPhoto\Gallery\Events\GalleryViewed;

/**
 * Story 6.3 — Email sent to the photographer when a client views
 * their gallery at a notification threshold.
 *
 * Subject varies: "Gallery Viewed" for first view, "Gallery Milestone"
 * for subsequent thresholds (5, 10, 25, 50).
 */
class GalleryViewedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $galleryName;
    public string $viewedByEmail;
    public int    $viewCount;
    public bool   $isFirstView;
    public string $viewedAt;
    public string $dashboardUrl;

    public function __construct(GalleryViewed $event, string $dashboardUrl)
    {
        $this->galleryName   = $event->galleryName;
        $this->viewedByEmail = $event->viewedByEmail;
        $this->viewCount     = $event->viewCount;
        $this->isFirstView   = $event->viewCount === 1;
        $this->viewedAt      = $event->viewedAt;
        $this->dashboardUrl  = $dashboardUrl;
    }

    public function envelope(): Envelope
    {
        $subject = $this->isFirstView
            ? "Gallery Viewed: {$this->galleryName}"
            : "Gallery Milestone: {$this->galleryName} — {$this->viewCount} views";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'prophoto-notifications::emails.gallery-viewed',
        );
    }
}
