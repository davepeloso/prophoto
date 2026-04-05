"use client"

import type { Photo, Tag, Filters } from "../types"
import { Button } from "./ui/button"
import { Label } from "./ui/label"
import { Checkbox } from "./ui/checkbox"
import { Slider } from "./ui/slider"
import { ScrollArea } from "./ui/scroll-area"
import { Badge } from "./ui/badge"
import { X, FolderOpen, FileText } from "lucide-react"
import { cn } from "../lib/utils"
import { useMemo } from "react"

interface FilterSidebarProps {
  open: boolean
  onClose: () => void
  photos: Photo[]
  filters: Filters
  onFiltersChange: (filters: Filters) => void
}

export function FilterSidebar({ open, onClose, photos, filters, onFiltersChange }: FilterSidebarProps) {
  const cameras = useMemo(() => [...new Set(photos.map((p) => p.camera))], [photos])

  const allTags = useMemo(() => {
    const tagMap = new Map<number, Tag>()
    photos.forEach(p => p.tags.forEach(t => tagMap.set(t.id, t)))
    return Array.from(tagMap.values())
  }, [photos])

  const projectTags = useMemo(() => allTags.filter(t => t.tag_type === 'project'), [allTags])
  const filenameTags = useMemo(() => allTags.filter(t => t.tag_type === 'filename'), [allTags])
  const normalTags = useMemo(() => allTags.filter(t => t.tag_type === 'normal'), [allTags])

  const toggleCamera = (camera: string) => {
    const newCameras = filters.cameras.includes(camera)
      ? filters.cameras.filter((c) => c !== camera)
      : [...filters.cameras, camera]
    onFiltersChange({ ...filters, cameras: newCameras })
  }

  const toggleTag = (tag: Tag) => {
    const newTags = filters.tags.some(t => t.id === tag.id)
      ? filters.tags.filter((t) => t.id !== tag.id)
      : [...filters.tags, tag]
    onFiltersChange({ ...filters, tags: newTags })
  }

  const clearFilters = () => {
    onFiltersChange({
      cameras: [],
      dateRange: null,
      apertureRange: null,
      isoRange: null,
      tags: [],
    })
  }

  const activeFilterCount =
    filters.cameras.length + filters.tags.length + (filters.apertureRange ? 1 : 0) + (filters.isoRange ? 1 : 0)

  return (
    <div
      className={cn(
        "w-64 border-r border-border bg-card flex flex-col transition-all duration-200",
        !open && "w-0 opacity-0 overflow-hidden",
      )}
    >
      <div className="h-12 px-4 flex items-center justify-between border-b border-border">
        <div className="flex items-center gap-2">
          <span className="font-medium text-sm">Filters</span>
          {activeFilterCount > 0 && (
            <Badge variant="secondary" className="text-xs px-1.5 py-0">
              {activeFilterCount}
            </Badge>
          )}
        </div>
        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={onClose}>
          <X className="h-4 w-4" />
        </Button>
      </div>

      <ScrollArea className="flex-1">
        <div className="p-4 space-y-6">
          {/* Camera Filter */}
          <div>
            <Label className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Camera</Label>
            <div className="mt-2 space-y-2">
              {cameras.map((camera) => (
                <div key={camera} className="flex items-center gap-2">
                  <Checkbox
                    id={`camera-${camera}`}
                    checked={filters.cameras.includes(camera)}
                    onCheckedChange={() => toggleCamera(camera)}
                  />
                  <label htmlFor={`camera-${camera}`} className="text-sm cursor-pointer">
                    {camera}
                  </label>
                </div>
              ))}
            </div>
          </div>

          {/* ISO Range */}
          <div>
            <Label className="text-xs font-medium text-muted-foreground uppercase tracking-wide">ISO Range</Label>
            <div className="mt-3 px-1">
              <Slider
                value={filters.isoRange || [100, 6400]}
                min={100}
                max={6400}
                step={100}
                onValueChange={(value) => onFiltersChange({ ...filters, isoRange: value as [number, number] })}
              />
              <div className="flex justify-between mt-1 text-xs text-muted-foreground">
                <span>{filters.isoRange?.[0] || 100}</span>
                <span>{filters.isoRange?.[1] || 6400}</span>
              </div>
            </div>
          </div>

          {/* Aperture Range */}
          <div>
            <Label className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Aperture Range</Label>
            <div className="mt-3 px-1">
              <Slider
                value={filters.apertureRange || [1.4, 16]}
                min={1.4}
                max={16}
                step={0.1}
                onValueChange={(value) => onFiltersChange({ ...filters, apertureRange: value as [number, number] })}
              />
              <div className="flex justify-between mt-1 text-xs text-muted-foreground">
                <span>f/{filters.apertureRange?.[0].toFixed(1) || "1.4"}</span>
                <span>f/{filters.apertureRange?.[1].toFixed(1) || "16"}</span>
              </div>
            </div>
          </div>

          {/* Quick Tags Section */}
          <div>
            <Label className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Quick Tags</Label>
            <div className="mt-2 space-y-3">
              {/* Project Tags */}
              <div>
                <div className="flex items-center gap-1.5 mb-1.5">
                  <FolderOpen className="h-3 w-3 text-blue-500" />
                  <span className="text-xs text-muted-foreground">Project</span>
                </div>
                <div className="flex flex-wrap gap-1.5">
                  {projectTags.slice(0, 4).map((tag) => (
                    <button
                      key={tag.id}
                      onClick={() => toggleTag(tag)}
                      className={cn(
                        "text-xs px-2 py-1 rounded-md transition-colors flex items-center gap-1",
                        filters.tags.some(t => t.id === tag.id) ? "bg-blue-500 text-white" : "bg-muted hover:bg-muted/80",
                      )}
                    >
                      {tag.name}
                    </button>
                  ))}
                </div>
              </div>

              {/* Filename Tags */}
              <div>
                <div className="flex items-center gap-1.5 mb-1.5">
                  <FileText className="h-3 w-3 text-green-500" />
                  <span className="text-xs text-muted-foreground">Filename</span>
                </div>
                <div className="flex flex-wrap gap-1.5">
                  {filenameTags.slice(0, 4).map((tag) => (
                    <button
                      key={tag.id}
                      onClick={() => toggleTag(tag)}
                      className={cn(
                        "text-xs px-2 py-1 rounded-md transition-colors flex items-center gap-1",
                        filters.tags.some(t => t.id === tag.id) ? "bg-green-500 text-white" : "bg-muted hover:bg-muted/80",
                      )}
                    >
                      {tag.name}
                    </button>
                  ))}
                </div>
              </div>

              {/* Normal Tags */}
              <div>
                <div className="flex items-center gap-1.5 mb-1.5">
                  <span className="text-xs text-muted-foreground">Other Tags</span>
                </div>
                <div className="flex flex-wrap gap-1.5">
                  {normalTags.slice(0, 8).map((tag) => (
                    <button
                      key={tag.id}
                      onClick={() => toggleTag(tag)}
                      className={cn(
                        "text-xs px-2 py-1 rounded-md transition-colors",
                        filters.tags.some(t => t.id === tag.id) ? "bg-primary text-primary-foreground" : "bg-muted hover:bg-muted/80",
                      )}
                    >
                      {tag.name}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </ScrollArea>

      {activeFilterCount > 0 && (
        <div className="p-4 border-t border-border">
          <Button variant="outline" size="sm" className="w-full bg-transparent" onClick={clearFilters}>
            Clear All Filters
          </Button>
        </div>
      )}
    </div>
  )
}
