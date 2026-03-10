"use client"

import type { Photo, Tag, Filters } from "../types"
import { Button } from "./ui/button"
import { Label } from "./ui/label"
import { Checkbox } from "./ui/checkbox"
import { Slider } from "./ui/slider"
import { Badge } from "./ui/badge"
import { X } from "lucide-react"
import { useMemo } from "react"

interface FiltersTabProps {
  photos: Photo[]
  filters: Filters
  onFiltersChange: (filters: Filters) => void
}

export function FiltersTab({ photos, filters, onFiltersChange }: FiltersTabProps) {
  const cameras = useMemo(() => [...new Set(photos.map((p) => p.camera))], [photos])
  const tags = useMemo(() => {
    const tagMap = new Map<number, Tag>()
    photos.forEach(p => p.tags.forEach(t => tagMap.set(t.id, t)))
    return Array.from(tagMap.values())
  }, [photos])

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

  const hasActiveFilters =
    filters.cameras.length > 0 ||
    filters.dateRange !== null ||
    filters.apertureRange !== null ||
    filters.isoRange !== null ||
    filters.tags.length > 0

  return (
    <div className="h-full flex flex-col p-4 gap-4 overflow-y-auto">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold">Filters</h2>
        {hasActiveFilters && (
          <Button variant="ghost" size="sm" onClick={clearFilters}>
            Clear All
          </Button>
        )}
      </div>

      {/* Active Filters */}
      {hasActiveFilters && (
        <div className="flex flex-wrap gap-2">
          {filters.cameras.map((camera) => (
            <Badge key={camera} variant="secondary" className="gap-1">
              {camera}
              <button onClick={() => toggleCamera(camera)} className="ml-1 hover:text-destructive">
                <X className="h-3 w-3" />
              </button>
            </Badge>
          ))}
          {filters.tags.map((tag) => (
            <Badge key={tag.id} variant="secondary" className="gap-1">
              {tag.name}
              <button onClick={() => toggleTag(tag)} className="ml-1 hover:text-destructive">
                <X className="h-3 w-3" />
              </button>
            </Badge>
          ))}
        </div>
      )}

      {/* Cameras */}
      {cameras.length > 0 && (
        <div>
          <Label className="text-sm font-medium mb-2 block">Camera</Label>
          <div className="space-y-2">
            {cameras.map((camera) => (
              <div key={camera} className="flex items-center space-x-2">
                <Checkbox
                  id={`camera-${camera}`}
                  checked={filters.cameras.includes(camera)}
                  onCheckedChange={() => toggleCamera(camera)}
                />
                <label
                  htmlFor={`camera-${camera}`}
                  className="text-sm font-normal leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                >
                  {camera}
                </label>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ISO Range */}
      <div>
        <Label className="text-sm font-medium mb-2 block">ISO Range</Label>
        <div className="px-1">
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
        <Label className="text-sm font-medium mb-2 block">Aperture Range</Label>
        <div className="px-1">
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

      {/* Tags */}
      {tags.length > 0 && (
        <div>
          <Label className="text-sm font-medium mb-2 block">Tags</Label>
          <div className="space-y-2">
            {tags.map((tag) => (
              <div key={tag.id} className="flex items-center space-x-2">
                <Checkbox
                  id={`tag-${tag.id}`}
                  checked={filters.tags.some(t => t.id === tag.id)}
                  onCheckedChange={() => toggleTag(tag)}
                />
                <label
                  htmlFor={`tag-${tag.id}`}
                  className="text-sm font-normal leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                >
                  {tag.name}
                </label>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
