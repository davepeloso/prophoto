<?php

namespace ProPhoto\Notifications\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use ProPhoto\Gallery\Events\ImageDownloaded;

/**
 * Story 6.2 — Email sent to the photographer when a client downloads
 * an image from a shared gallery.
 *
 * Contains gallery name, image filename, client email, download stats,
 * and a link to the gallery in the Filament admin panel.
 */
class DownloadNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string  $galleryName;
    public string  $imageFilename;
    public string  $downloadedByEmail;
    public int     $shareDownloadCount;
    public ?int    $shareMaxDownloads;
    public int     $galleryDownloadCount;
    public string  $downloadedAt;
    public string  $dashboardUrl;

    public function __construct(ImageDownloaded $event, string $dashboardUrl)
    {
        $this->galleryName          = $event->galleryName;
        $this->imageFilename        = $event->imageFilename;
        $this->downloadedByEmail    = $event->downloadedByEmail;
        $this->shareDownloadCount   = $event->shareDownloadCount;
        $this->shareMaxDownloads    = $event->shareMaxDownloads;
        $this->galleryDownloadCount = $event->galleryDownloadCount;
        $this->downloadedAt         = $event->downloadedAt;
        $this->dashboardUrl         = $dashboardUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Image Downloaded: {$this->galleryName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'prophoto-notifications::emails.image-downloaded',
        );
    }
}
