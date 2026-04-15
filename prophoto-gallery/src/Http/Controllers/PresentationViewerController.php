<?php

namespace ProPhoto\Gallery\Http\Controllers;

use Illuminate\Routing\Controller;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Services\ViewerTemplateRegistry;

/**
 * Story 3.2 — Presentation gallery viewer.
 *
 * Renders a responsive image grid for presentation-type galleries.
 * No identity gate, no pipeline UI — view only with optional downloads.
 *
 * This controller is called by GalleryViewerController after share
 * validation and activity logging are complete.
 */
class PresentationViewerController extends Controller
{
    /**
     * Render the presentation gallery viewer.
     *
     * Images are eager-loaded with asset derivatives to avoid N+1
     * queries when resolving thumbnail and full-size URLs.
     */
    public function show(Gallery $gallery, GalleryShare $share)
    {
        $images = $gallery->imagesWithAssets()
            ->orderBy('sort_order')
            ->get();

        // Pre-build the lightbox JSON data — Blade's compiler can't handle
        // closures inside @json(), so we prepare it in the controller.
        $lightboxData = $images->map(function ($img) {
            return [
                'thumbUrl' => $img->resolvedThumbnailUrl(),
                'fullUrl'  => $img->resolved_url ?? $img->resolvedThumbnailUrl(),
                'title'    => $img->title,
                'caption'  => $img->caption,
                'alt'      => $img->alt_text ?? $img->title ?? $img->original_filename,
                'filename' => $img->original_filename,
            ];
        })->values();

        // Story 7.4 — Dynamic view resolution from viewer_template
        $registry = app(ViewerTemplateRegistry::class);
        $templateSlug = $gallery->getEffectiveViewerTemplate();
        $viewName = $registry->resolveView('presentation', $templateSlug);
        $fontsUrl = $registry->fontsUrl($templateSlug);

        return view($viewName, [
            'gallery'      => $gallery,
            'share'        => $share,
            'images'       => $images,
            'lightboxData' => $lightboxData,
            'canDownload'  => (bool) $share->can_download,
            'fontsUrl'     => $fontsUrl,
        ]);
    }
}
