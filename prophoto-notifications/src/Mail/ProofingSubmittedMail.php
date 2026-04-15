<?php

namespace ProPhoto\Notifications\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use ProPhoto\Gallery\Events\GallerySubmitted;

/**
 * Story 5.2 — Email sent to the photographer when a client submits
 * their proofing selections.
 *
 * Contains gallery name, client email, approval stats, and a link
 * to the gallery in the Filament admin panel.
 */
class ProofingSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $galleryName;
    public string $submittedByEmail;
    public int    $approvedCount;
    public int    $pendingCount;
    public int    $totalImages;
    public string $submittedAt;
    public string $dashboardUrl;

    public function __construct(GallerySubmitted $event, string $dashboardUrl)
    {
        $this->galleryName      = $event->galleryName;
        $this->submittedByEmail = $event->submittedByEmail;
        $this->approvedCount    = $event->approvedCount;
        $this->pendingCount     = $event->pendingCount;
        $this->totalImages      = $event->totalImages;
        $this->submittedAt      = $event->submittedAt;
        $this->dashboardUrl     = $dashboardUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Proofing Submitted: {$this->galleryName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'prophoto-notifications::emails.proofing-submitted',
        );
    }
}
