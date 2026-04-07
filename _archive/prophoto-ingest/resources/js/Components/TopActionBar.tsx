"use client"

import type React from "react"

import { Button } from "./ui/button"
import { Switch } from "./ui/switch"
import { Label } from "./ui/label"
import { Progress } from "./ui/progress"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "./ui/select"
import { ImagePlus, Settings, Upload } from "lucide-react"
import { useRef, useCallback } from "react"
import { router } from "@inertiajs/react"
import { ThemeToggle } from "./ThemeToggle"

interface TopActionBarProps {
  totalCount: number
  selectedCount: number
  showCulled: boolean
  onShowCulledChange: (show: boolean) => void
  sortBy: "date" | "camera" | "userOrder"
  onSortChange: (sort: "date" | "camera" | "userOrder") => void
  onAddPhotos: (files: FileList) => void
  onIngest: () => void
  ingestProgress: number | null
}

export function TopActionBar({
  totalCount,
  selectedCount,
  showCulled,
  onShowCulledChange,
  sortBy,
  onSortChange,
  onAddPhotos,
  onIngest,
  ingestProgress,
}: TopActionBarProps) {
  const fileInputRef = useRef<HTMLInputElement>(null)

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault()
      if (e.dataTransfer.files.length) {
        onAddPhotos(e.dataTransfer.files)
      }
    },
    [onAddPhotos],
  )

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault()
  }, [])

  return (
    <div
      className="h-14 border-b border-border bg-card px-4 flex items-center gap-4"
      onDrop={handleDrop}
      onDragOver={handleDragOver}
    >
      <input
        ref={fileInputRef}
        type="file"
        accept="image/*,.dng,.DNG,.cr2,.CR2,.cr3,.CR3,.nef,.NEF,.arw,.ARW,.orf,.ORF,.rw2,.RW2,.pef,.PEF,.raf,.RAF,.srw,.SRW,.x3f,.X3F"
        multiple
        className="hidden"
        onChange={(e) => e.target.files && onAddPhotos(e.target.files)}
      />

      <Button variant="outline" size="sm" onClick={() => fileInputRef.current?.click()} className="gap-2">
        <ImagePlus className="h-4 w-4" />
        Add Photos
      </Button>

      <div className="h-6 w-px bg-border" />

      <div className="flex items-center gap-2">
        <Switch id="show-culled" checked={showCulled} onCheckedChange={onShowCulledChange} />
        <Label htmlFor="show-culled" className="text-sm text-muted-foreground">
          Show Culled
        </Label>
      </div>

      <Select value={sortBy} onValueChange={onSortChange as (value: string) => void}>
        <SelectTrigger className="w-[140px] h-8">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="date">Date Taken</SelectItem>
          <SelectItem value="camera">Camera</SelectItem>
          <SelectItem value="userOrder">User Order</SelectItem>
        </SelectContent>
      </Select>

      <div className="flex-1" />

      <div className="flex items-center gap-2 text-sm">
        <span className="text-muted-foreground">
          <span className="font-medium text-foreground">{selectedCount}</span> of {totalCount} selected
        </span>
      </div>

      <div className="h-6 w-px bg-border" />

      <ThemeToggle />

      <Button
        variant="ghost"
        size="sm"
        onClick={() => router.visit('/ingest/settings')}
        className="gap-2"
      >
        <Settings className="h-4 w-4" />
        Settings
      </Button>

      {ingestProgress !== null ? (
        <div className="flex items-center gap-3 min-w-[200px]">
          <Progress value={ingestProgress} className="flex-1 h-2" />
          <span className="text-sm text-muted-foreground whitespace-nowrap">{Math.round(ingestProgress)}%</span>
        </div>
      ) : (
        <Button size="sm" disabled={selectedCount === 0} onClick={onIngest} className="gap-2">
          <Upload className="h-4 w-4" />
          Ingest Selected
        </Button>
      )}
    </div>
  )
}
