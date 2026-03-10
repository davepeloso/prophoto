<?php

namespace ProPhoto\Gallery\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShareResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'gallery_id' => $this->gallery_id,
            'share_token' => $this->share_token,
            'share_url' => route('api.shares.show', ['token' => $this->share_token]),
            'has_password' => !empty($this->password_hash),
            'expires_at' => $this->expires_at?->toISOString(),
            'max_views' => $this->max_downloads,
            'view_count' => $this->access_count,
            'allow_downloads' => $this->can_download,
            'allow_comments' => $this->can_comment,
            'settings' => [
                'can_view' => $this->can_view,
                'can_download' => $this->can_download,
                'can_approve' => $this->can_approve,
                'can_comment' => $this->can_comment,
                'can_share' => $this->can_share,
                'ip_whitelist' => $this->ip_whitelist,
                'message' => $this->message,
            ],
            'is_valid' => $this->isValid(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'gallery' => new GalleryResource($this->whenLoaded('gallery')),
            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }),
        ];
    }
}
