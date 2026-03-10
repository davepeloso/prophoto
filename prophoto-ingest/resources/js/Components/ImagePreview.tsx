"use client"

import type React from "react"

import { useState, useCallback } from "react"
import type { Photo } from "../types"
import { cn } from "../lib/utils"
import { Button } from "./ui/button"
import { RotateCw, X, Star, ImageOff } from "lucide-react"
import { ScrollArea, ScrollBar } from "./ui/scroll-area"

interface ImagePreviewProps {
  photo: Photo | null
  selectedPhotos: Photo[]
  onCull: (ids: string[]) => void
  onStar: (ids: string[], rating?: number) => void
  onRotate: (ids: string[], direction: "cw" | "ccw") => void
}

export function ImagePreview({ photo, selectedPhotos, onCull, onStar, onRotate }: ImagePreviewProps) {
  const [isZoomed, setIsZoomed] = useState(false)
  const [zoomPosition, setZoomPosition] = useState({ x: 50, y: 50 })

  const handleMouseMove = useCallback(
    (e: React.MouseEvent<HTMLDivElement>) => {
      if (!isZoomed) return
      const rect = e.currentTarget.getBoundingClientRect()
      const x = ((e.clientX - rect.left) / rect.width) * 100
      const y = ((e.clientY - rect.top) / rect.height) * 100
      setZoomPosition({ x, y })
    },
    [isZoomed],
  )

  const ids = selectedPhotos.map((p) => p.id)

  if (!photo) {
    return (
      <div className="h-full flex items-center justify-center bg-muted/20">
        <div className="text-center text-muted-foreground">
          <ImageOff className="h-16 w-16 mx-auto mb-4 opacity-40" />
          <p className="text-lg">No image selected</p>
          <p className="text-sm mt-1">Click a thumbnail to preview</p>
        </div>
      </div>
    )
  }

  
  return (
    <div className="h-full flex flex-col bg-muted/20">
      {/* Multi-selection filmstrip */}
      {selectedPhotos.length > 1 && (
        <div className="h-20 border-b border-border bg-card/50 p-2">
          <div className="flex items-center gap-2 h-full">
            <span className="text-sm text-muted-foreground px-2 whitespace-nowrap">
              {selectedPhotos.length} images selected
            </span>
            <ScrollArea className="flex-1">
              <div className="flex gap-1">
                {selectedPhotos.map((p) => (
                  <img
                    key={p.id}
                    src={p.thumbnail || "/placeholder.svg"}
                    alt={p.filename}
                    className={cn(
                      "h-14 w-14 object-cover rounded flex-shrink-0",
                      p.id === photo.id && "ring-2 ring-primary",
                    )}
                  />
                ))}
              </div>
              <ScrollBar orientation="horizontal" />
            </ScrollArea>
          </div>
        </div>
      )}

      {/* Main preview area */}
      <div
        className="flex-1 relative overflow-hidden flex items-center justify-center p-4"
        onMouseEnter={() => setIsZoomed(true)}
        onMouseLeave={() => setIsZoomed(false)}
        onMouseMove={handleMouseMove}
      >
        <div
          className={cn(
            "relative max-w-full max-h-full transition-transform duration-200",
            isZoomed && "cursor-zoom-in",
          )}
          style={{
            transform: isZoomed ? "scale(2)" : "scale(1)",
            transformOrigin: `${zoomPosition.x}% ${zoomPosition.y}%`,
          }}
        >
          <img
            src={photo.fullSize || "/placeholder.svg"}
            alt={photo.filename}
            className={cn(
              "max-w-full max-h-[calc(100vh-280px)] object-contain rounded-md",
              photo.culled && "grayscale opacity-70",
            )}
            style={{ transform: `rotate(${photo.rotation}deg)` }}
          />
        </div>

        {/* Culled overlay */}
        {photo.culled && (
          <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
            <div className="bg-red-950/60 text-red-400 px-6 py-3 rounded-lg text-lg font-medium">CULLED</div>
          </div>
        )}
      </div>

      {/* Bottom overlay bar */}
      <div className="border-t border-border bg-card/80 backdrop-blur-sm p-3">
        {/* <div className="flex items-center justify-between">
          <div className="text-sm text-muted-foreground">{exifString}</div> */}

        <div className="flex items-center gap-2">
            <Button variant="ghost" size="sm" onClick={() => onRotate(ids, "ccw")}>
              <RotateCw className="h-4 w-4 scale-x-[-1]" />
            </Button>
            <Button variant="ghost" size="sm" onClick={() => onRotate(ids, "cw")}>
              <RotateCw className="h-4 w-4" />
            </Button>

            <div className="h-4 w-px bg-border mx-1" />

            <Button
              variant={photo.culled ? "secondary" : "ghost"}
              size="sm"
              onClick={() => onCull(ids)}
              className={cn(photo.culled && "text-red-400")}
            >
              <X className="h-4 w-4 mr-1" />
              Cull
            </Button>

            {/* Star rating */}
            <div className="flex items-center gap-0.5 ml-2">
              {[1, 2, 3, 4, 5].map((rating) => (
                <button
                  key={rating}
                  onClick={() => onStar(ids, rating === photo.rating ? 0 : rating)}
                  className={cn(
                    "p-0.5 transition-colors",
                    rating <= photo.rating ? "text-yellow-400" : "text-muted-foreground/40 hover:text-muted-foreground",
                  )}
                >
                  <Star className={cn("h-4 w-4", rating <= photo.rating && "fill-current")} />
                </button>
              ))}
            </div>
        </div>

        <div className="text-xs text-muted-foreground mt-2">
          Keyboard shortcuts: <kbd className="px-1 py-0.5 bg-muted rounded text-[10px]">C</kbd> cull •{" "}
          <kbd className="px-1 py-0.5 bg-muted rounded text-[10px]">S</kbd> star •{" "}
          <kbd className="px-1 py-0.5 bg-muted rounded text-[10px]">1-5</kbd> rate •{" "}
          <kbd className="px-1 py-0.5 bg-muted rounded text-[10px]">E</kbd> enhance •{" "}
          <kbd className="px-1 py-0.5 bg-muted rounded text-[10px]">A</kbd> select all •{" "}
          <kbd className="px-1 py-0.5 bg-muted rounded text-[10px]">D</kbd> deselect •{" "}
          <kbd className="px-1 py-0.5 bg-muted rounded text-[10px]">←</kbd>
          <kbd className="px-1 py-0.5 bg-muted rounded text-[10px]">→</kbd> navigate
        </div>
      </div>
    </div>
  )
}
