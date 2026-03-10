<?php

namespace ProPhoto\Gallery\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'gallery_id' => $this->gallery_id,
            'asset_id' => $this->asset_id,
            'filename' => $this->filename,
            'file_path' => $this->resolved_file_path,
            'url' => $this->resolved_url,
            'thumbnail_url' => $this->resolved_thumbnail_url,
            'file_size' => $this->resolved_file_size,
            'mime_type' => $this->resolved_mime_type,
            'width' => $this->width,
            'height' => $this->height,
            'title' => $this->title,
            'description' => $this->description,
            'alt_text' => $this->alt_text,
            'is_marketing_approved' => $this->is_marketing_approved,
            'ai_generated' => $this->ai_generated,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                        'color' => $tag->color,
                    ];
                });
            }),

            // Stats
            'rating' => $this->when(
                $this->interactions()->where('interaction_type', 'rating')->exists(),
                function () {
                    return $this->interactions()
                        ->where('interaction_type', 'rating')
                        ->avg('metadata->rating');
                }
            ),
            'likes_count' => $this->when(
                isset($this->likes_count),
                $this->likes_count
            ),
        ];
    }
}
