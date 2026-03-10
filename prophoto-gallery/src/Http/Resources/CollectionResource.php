<?php

namespace ProPhoto\Gallery\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'user_id' => $this->created_by_user_id,
            'organization_id' => $this->organization_id,
            'cover_image_url' => $this->cover_image_id,
            'is_public' => $this->is_public,
            'sort_order' => $this->sort_order,
            'settings' => $this->settings,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
            'galleries' => GalleryResource::collection($this->whenLoaded('galleries')),

            // Stats
            'galleries_count' => $this->when(
                isset($this->galleries_count),
                $this->galleries_count
            ),
        ];
    }
}
