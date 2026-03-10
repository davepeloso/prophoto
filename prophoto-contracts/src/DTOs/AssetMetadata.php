<?php

namespace ProPhoto\Contracts\DTOs;

use ProPhoto\Contracts\Enums\AssetType;

readonly class AssetMetadata
{
    public function __construct(
        public AssetType $type,
        public string $mimeType,
        public int $fileSize,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $cameraMake = null,
        public ?string $cameraModel = null,
        public ?string $lens = null,
        public ?string $focalLength = null,
        public ?string $aperture = null,
        public ?string $shutterSpeed = null,
        public ?int $iso = null,
        public ?string $dateTaken = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public array $rawExif = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: AssetType::from($data['type'] ?? 'unknown'),
            mimeType: $data['mime_type'] ?? 'application/octet-stream',
            fileSize: $data['file_size'] ?? 0,
            width: $data['width'] ?? null,
            height: $data['height'] ?? null,
            cameraMake: $data['camera_make'] ?? null,
            cameraModel: $data['camera_model'] ?? null,
            lens: $data['lens'] ?? null,
            focalLength: $data['focal_length'] ?? null,
            aperture: $data['aperture'] ?? null,
            shutterSpeed: $data['shutter_speed'] ?? null,
            iso: $data['iso'] ?? null,
            dateTaken: $data['date_taken'] ?? null,
            latitude: $data['latitude'] ?? null,
            longitude: $data['longitude'] ?? null,
            rawExif: $data['raw_exif'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'mime_type' => $this->mimeType,
            'file_size' => $this->fileSize,
            'width' => $this->width,
            'height' => $this->height,
            'camera_make' => $this->cameraMake,
            'camera_model' => $this->cameraModel,
            'lens' => $this->lens,
            'focal_length' => $this->focalLength,
            'aperture' => $this->aperture,
            'shutter_speed' => $this->shutterSpeed,
            'iso' => $this->iso,
            'date_taken' => $this->dateTaken,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'raw_exif' => $this->rawExif,
        ];
    }
}
