"use client"

import type React from "react"

import { useState, useCallback } from "react"
import type { Photo } from "../types"
import { cn } from "../lib/utils"
import { Star, X, RotateCw, Eye, FileImage, Sparkles, Tag } from "lucide-react"
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuShortcut,
  ContextMenuSub,
  ContextMenuSubContent,
  ContextMenuSubTrigger,
  ContextMenuTrigger,
} from "./ui/context-menu"
import { Skeleton } from "./ui/skeleton"
import { ScrollArea } from "./ui/scroll-area"

interface ThumbnailBrowserProps {
  photos: Photo[]
  selectedIds: Set<string>
  onSelect: (id: string, multi: boolean, range: boolean) => void
  onCull: (ids: string[]) => void
  onStar: (ids: string[], rating?: number) => void
  onRotate: (ids: string[], direction: "cw" | "ccw") => void
  onReorder: (fromId: string, toId: string) => void
  onEnhance?: (ids: string[]) => void
  onSelectAll: () => void
  onClearSelection: () => void
  onOpenTagsModal: () => void
  isLoading: boolean
}

export function ThumbnailBrowser({
  photos,
  selectedIds,
  onSelect,
  onCull,
  onStar,
  onRotate,
  onEnhance,
  onReorder,
  onSelectAll,
  onClearSelection,
  onOpenTagsModal,
  isLoading,
}: ThumbnailBrowserProps) {
  const [draggedId, setDraggedId] = useState<string | null>(null)
  const [dragOverId, setDragOverId] = useState<string | null>(null)

  const handleClick = useCallback(
    (e: React.MouseEvent, id: string) => {
      onSelect(id, e.metaKey || e.ctrlKey, e.shiftKey)
    },
    [onSelect],
  )

  const handleDragStart = useCallback((e: React.DragEvent, id: string) => {
    setDraggedId(id)
    e.dataTransfer.effectAllowed = "move"
  }, [])

  const handleDragOver = useCallback(
    (e: React.DragEvent, id: string) => {
      e.preventDefault()
      if (draggedId && draggedId !== id) {
        setDragOverId(id)
      }
    },
    [draggedId],
  )

  const handleDrop = useCallback(
    (e: React.DragEvent, id: string) => {
      e.preventDefault()
      if (draggedId && draggedId !== id) {
        onReorder(draggedId, id)
      }
      setDraggedId(null)
      setDragOverId(null)
    },
    [draggedId, onReorder],
  )

  const handleDragEnd = useCallback(() => {
    setDraggedId(null)
    setDragOverId(null)
  }, [])

  if (isLoading) {
    return (
      <ScrollArea className="h-full bg-muted/30">
        <div className="p-2 grid grid-cols-3 gap-1.5">
          {Array.from({ length: 24 }).map((_, i) => (
            <Skeleton key={i} className="aspect-square rounded-md" />
          ))}
        </div>
      </ScrollArea>
    )
  }

  if (photos.length === 0) {
    return (
      <div className="h-full flex items-center justify-center bg-muted/30">
        <div className="text-center text-muted-foreground p-8">
          <FileImage className="h-12 w-12 mx-auto mb-3 opacity-50" />
          <p className="text-sm">No photos to display</p>
          <p className="text-xs mt-1">Add photos or adjust filters</p>
        </div>
      </div>
    )
  }

  return (
    <ScrollArea className="h-full bg-muted/30">
      <div className="p-2 grid grid-cols-3 gap-1.5">
        {photos.map((photo) => (
          <ContextMenu key={photo.id}>
            <ContextMenuTrigger>
              <div
                draggable
                onDragStart={(e) => handleDragStart(e, photo.id)}
                onDragOver={(e) => handleDragOver(e, photo.id)}
                onDrop={(e) => handleDrop(e, photo.id)}
                onDragEnd={handleDragEnd}
                onClick={(e) => handleClick(e, photo.id)}
                className={cn(
                  "relative aspect-square rounded-md overflow-hidden cursor-pointer transition-all duration-150",
                  "ring-2 ring-offset-1 ring-offset-background",
                  selectedIds.has(photo.id)
                    ? "ring-primary scale-[0.97]"
                    : "ring-transparent hover:ring-muted-foreground/30",
                  dragOverId === photo.id && "ring-primary ring-offset-2",
                  draggedId === photo.id && "opacity-50",
                )}
              >
                <img
                  src={photo.thumbnail || "/placeholder.svg"}
                  alt={photo.filename}
                  className={cn("w-full h-full object-cover", photo.culled && "grayscale opacity-60")}
                  style={{ transform: `rotate(${photo.rotation}deg)` }}
                  loading="lazy"
                />

                {/* Culled overlay */}
                {photo.culled && (
                  <div className="absolute inset-0 flex items-center justify-center bg-red-950/40">
                    <X className="h-8 w-8 text-red-400" />
                  </div>
                )}

                {/* Star badge */}
                {photo.starred && (
                  <div className="absolute top-1 left-1">
                    <Star className="h-4 w-4 fill-yellow-400 text-yellow-400 drop-shadow" />
                  </div>
                )}

                {/* File type badge */}
                <div className="absolute top-1 right-1 bg-black/60 text-[10px] font-medium text-white px-1 py-0.5 rounded">
                  {photo.fileType}
                </div>

                {/* Rating badge */}
                {photo.rating > 0 && (
                  <div className="absolute bottom-1 left-1 flex gap-0.5">
                    {Array.from({ length: photo.rating }).map((_, i) => (
                      <div key={i} className="w-1.5 h-1.5 rounded-full bg-yellow-400" />
                    ))}
                  </div>
                )}
              </div>
            </ContextMenuTrigger>
            <ContextMenuContent>
              <ContextMenuItem onClick={onSelectAll}>
                Select All
                <ContextMenuShortcut>A</ContextMenuShortcut>
              </ContextMenuItem>
              <ContextMenuItem onClick={onClearSelection}>
                Deselect All
                <ContextMenuShortcut>D</ContextMenuShortcut>
              </ContextMenuItem>
              <ContextMenuSeparator />
              <ContextMenuItem onClick={() => onCull([photo.id])}>
                <X className="h-4 w-4 mr-2" />
                {photo.culled ? "Uncull" : "Cull"}
                <ContextMenuShortcut>C</ContextMenuShortcut>
              </ContextMenuItem>
              <ContextMenuItem onClick={() => onStar([photo.id])}>
                <Star className="h-4 w-4 mr-2" />
                {photo.starred ? "Unstar" : "Star"}
                <ContextMenuShortcut>S</ContextMenuShortcut>
              </ContextMenuItem>
              <ContextMenuSub>
                <ContextMenuSubTrigger>
                  <Star className="h-4 w-4 mr-2" />
                  Set Rating
                </ContextMenuSubTrigger>
                <ContextMenuSubContent>
                  <ContextMenuItem onClick={() => onStar([photo.id], 1)}>
                    1 Star
                    <ContextMenuShortcut>1</ContextMenuShortcut>
                  </ContextMenuItem>
                  <ContextMenuItem onClick={() => onStar([photo.id], 2)}>
                    2 Stars
                    <ContextMenuShortcut>2</ContextMenuShortcut>
                  </ContextMenuItem>
                  <ContextMenuItem onClick={() => onStar([photo.id], 3)}>
                    3 Stars
                    <ContextMenuShortcut>3</ContextMenuShortcut>
                  </ContextMenuItem>
                  <ContextMenuItem onClick={() => onStar([photo.id], 4)}>
                    4 Stars
                    <ContextMenuShortcut>4</ContextMenuShortcut>
                  </ContextMenuItem>
                  <ContextMenuItem onClick={() => onStar([photo.id], 5)}>
                    5 Stars
                    <ContextMenuShortcut>5</ContextMenuShortcut>
                  </ContextMenuItem>
                  <ContextMenuSeparator />
                  <ContextMenuItem onClick={() => onStar([photo.id], 0)}>
                    Clear Rating
                  </ContextMenuItem>
                </ContextMenuSubContent>
              </ContextMenuSub>
              <ContextMenuSeparator />
              {onEnhance && (
                <ContextMenuItem onClick={() => onEnhance([photo.id])}>
                  <Sparkles className="h-4 w-4 mr-2" />
                  Enhance Quality
                  <ContextMenuShortcut>E</ContextMenuShortcut>
                </ContextMenuItem>
              )}
              <ContextMenuItem onClick={onOpenTagsModal}>
                <Tag className="h-4 w-4 mr-2" />
                Add Tags
                <ContextMenuShortcut>T</ContextMenuShortcut>
              </ContextMenuItem>
              <ContextMenuSeparator />
              <ContextMenuItem onClick={() => onRotate([photo.id], "cw")}>
                <RotateCw className="h-4 w-4 mr-2" />
                Rotate CW
              </ContextMenuItem>
              <ContextMenuItem onClick={() => onRotate([photo.id], "ccw")}>
                <RotateCw className="h-4 w-4 mr-2 scale-x-[-1]" />
                Rotate CCW
              </ContextMenuItem>
              <ContextMenuSeparator />
              <ContextMenuItem>
                <Eye className="h-4 w-4 mr-2" />
                View Metadata
              </ContextMenuItem>
            </ContextMenuContent>
          </ContextMenu>
        ))}
      </div>
    </ScrollArea>
  )
}
