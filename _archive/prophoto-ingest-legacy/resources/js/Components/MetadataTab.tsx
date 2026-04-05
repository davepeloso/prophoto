"use client"

import type { Photo } from "../types"
import { ScrollArea } from "./ui/scroll-area"
import { Separator } from "./ui/separator"

interface MetadataTabProps {
  photo: Photo | null
}

export function MetadataTab({ photo }: MetadataTabProps) {
  if (!photo) {
    return (
      <div className="h-full flex items-center justify-center text-muted-foreground">
        <p className="text-sm">Select a photo to view metadata</p>
      </div>
    )
  }

  const formatDate = (date: string) => {
    return new Date(date).toLocaleString("en-US", {
      weekday: "short",
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    })
  }

  const MetadataRow = ({ label, value }: { label: string; value: string | number }) => (
    <div className="flex justify-between items-center py-2">
      <span className="text-xs text-muted-foreground">{label}</span>
      <span className="text-sm font-medium">{value}</span>
    </div>
  )

  return (
    <ScrollArea className="h-full">
      <div className="p-4">
        {/* Basic Info */}
        <div className="space-y-1">
          <MetadataRow label="Date Taken" value={formatDate(photo.dateTaken)} />
          <MetadataRow label="Camera" value={photo.camera} />
          <MetadataRow label="Lens" value={photo.lens} />
        </div>

        <Separator className="my-4" />

        {/* Exposure Settings */}
        <div className="space-y-1">
          <MetadataRow label="Aperture" value={`f/${photo.aperture}`} />
          <MetadataRow label="Shutter Speed" value={photo.shutterSpeed} />
          <MetadataRow label="ISO" value={photo.iso} />
          <MetadataRow label="Focal Length" value={`${photo.focalLength}mm`} />
        </div>

        <Separator className="my-4" />

        {/* Camera Settings */}
        <div className="space-y-1">
          <MetadataRow label="Exposure Mode" value="Manual" />
          <MetadataRow label="Metering Mode" value="Evaluative" />
          <MetadataRow label="White Balance" value="Auto" />
          <MetadataRow label="Color Space" value="sRGB" />
        </div>

        <Separator className="my-4" />

        {/* File Info */}
        <div className="space-y-1">
          <MetadataRow label="Dimensions" value={photo.dimensions} />
          <MetadataRow label="File Size" value={photo.fileSize} />
          <div className="flex justify-between items-center py-2">
            <span className="text-xs text-muted-foreground">Filename</span>
            <span className="text-sm font-medium truncate max-w-[200px]">{photo.filename}</span>
          </div>
        </div>

        {photo.gps && (
          <>
            <Separator className="my-4" />
            <div className="space-y-1">
              <MetadataRow label="Location" value={photo.gps.location} />
            </div>
          </>
        )}
      </div>
    </ScrollArea>
  )
}
