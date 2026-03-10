<?php

namespace ProPhoto\Gallery\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GalleryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_image_id' => $this->cover_image_id,
            'cover_image_url' => $this->coverImage
                ? (
                    $this->coverImage->resolved_url
                    ?? ($this->coverImage->file_path
                        ? asset('storage/' . $this->coverImage->file_path)
                        : null)
                )
                : null,
            'status' => $this->status,
            'user_id' => $this->user_id,
            'organization_id' => $this->organization_id,
            'settings' => $this->settings,
            'client_message' => $this->client_message,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_keywords' => $this->seo_keywords,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'organization' => $this->whenLoaded('organization', function () {
                return [
                    'id' => $this->organization->id,
                    'name' => $this->organization->name,
                ];
            }),
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'collections' => CollectionResource::collection($this->whenLoaded('collections')),

            // Stats
            'images_count' => $this->when(
                isset($this->images_count),
                $this->images_count
            ),
        ];
    }
}
