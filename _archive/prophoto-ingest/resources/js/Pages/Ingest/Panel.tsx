"use client"

import { useState, useCallback, useEffect, useMemo } from "react"
import { TopActionBar } from "../../Components/TopActionBar"
import { ThumbnailBrowser } from "../../Components/ThumbnailBrowser"
import { ImagePreview } from "../../Components/ImagePreview"
import { RightPanel } from "../../Components/RightPanel"
import { TagsModal } from "../../Components/TagsModal"
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from "../../Components/ui/resizable"
import { usePreviewPolling } from "../../hooks/usePreviewPolling"
import type { Photo, Tag, TagType, Filters } from "../../types"

interface Props {
  initialPhotos: Photo[]
  availableTags: Tag[]
  quickTags: string[]
  config: {
    maxFileSize: number
    acceptedTypes: string[]
  }
}

export default function Panel({ initialPhotos, availableTags }: Props) {
  const [photos, setPhotos] = useState<Photo[]>(initialPhotos)
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set())
  const [showCulled, setShowCulled] = useState(false)
  const [sortBy, setSortBy] = useState<"date" | "camera" | "userOrder">("date")
  const [tagsModalOpen, setTagsModalOpen] = useState(false)
  const [cumulativeMode, setCumulativeMode] = useState(false)
  const [ingestProgress, setIngestProgress] = useState<number | null>(null)
  const [isLoading, setIsLoading] = useState(false)

  const [filters, setFilters] = useState<Filters>({
    cameras: [],
    dateRange: null,
    apertureRange: null,
    isoRange: null,
    tags: [],
  })

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

  // Handle preview status updates from polling
  const handlePreviewUpdates = useCallback((updates: Partial<Photo>[]) => {
    setPhotos(prev => prev.map(photo => {
      const update = updates.find(u => u.id === photo.id)
      if (update) {
        return {
          ...photo,
          thumbnail: update.thumbnail ?? photo.thumbnail,
          fullSize: update.fullSize ?? photo.fullSize,
          previewStatus: update.previewStatus ?? photo.previewStatus,
          previewReady: update.previewReady ?? photo.previewReady,
        }
      }
      return photo
    }))
  }, [])

  // Enable preview polling
  usePreviewPolling({
    photos,
    onPhotosUpdate: handlePreviewUpdates,
    pollInterval: 2000,
    enabled: true,
  })

  const filteredPhotos = photos.filter((photo) => {
    if (!showCulled && photo.culled) return false
    if (filters.cameras.length && !filters.cameras.includes(photo.camera)) return false
    if (filters.tags.length && !filters.tags.some((t) => photo.tags.some(tag => tag.name === t.name))) return false
    if (filters.isoRange) {
      if (photo.iso < filters.isoRange[0] || photo.iso > filters.isoRange[1]) return false
    }
    if (filters.apertureRange) {
      if (photo.aperture < filters.apertureRange[0] || photo.aperture > filters.apertureRange[1]) return false
    }
    return true
  })

  const sortedPhotos = [...filteredPhotos].sort((a, b) => {
    if (sortBy === "date") return new Date(b.dateTaken).getTime() - new Date(a.dateTaken).getTime()
    if (sortBy === "camera") return a.camera.localeCompare(b.camera)
    return a.userOrder - b.userOrder
  })

  const selectedPhotos = sortedPhotos.filter((p) => selectedIds.has(p.id))
  const activePhoto = selectedPhotos.length >= 1 ? selectedPhotos[0] : null

  const handleSelect = useCallback(
    (id: string, multi: boolean, range: boolean) => {
      setSelectedIds((prev) => {
        const newSet = new Set(prev)
        if (range && prev.size > 0) {
          const lastSelected = Array.from(prev).pop()!
          const lastIndex = sortedPhotos.findIndex((p) => p.id === lastSelected)
          const currentIndex = sortedPhotos.findIndex((p) => p.id === id)
          const [start, end] = lastIndex < currentIndex ? [lastIndex, currentIndex] : [currentIndex, lastIndex]
          for (let i = start; i <= end; i++) {
            newSet.add(sortedPhotos[i].id)
          }
        } else if (multi || cumulativeMode) {
          if (newSet.has(id)) newSet.delete(id)
          else newSet.add(id)
        } else {
          newSet.clear()
          newSet.add(id)
        }
        return newSet
      })
    },
    [sortedPhotos, cumulativeMode],
  )

  const handleSelectByFilter = useCallback(
    (ids: string[], additive: boolean) => {
      setSelectedIds((prev) => {
        if (additive || cumulativeMode) {
          const newSet = new Set(prev)
          ids.forEach((id) => newSet.add(id))
          return newSet
        }
        return new Set(ids)
      })
    },
    [cumulativeMode],
  )

  const handleSelectAll = useCallback(() => {
    setSelectedIds(new Set(sortedPhotos.map((p) => p.id)))
  }, [sortedPhotos])

  const handleClearSelection = useCallback(() => {
    setSelectedIds(new Set())
  }, [])

  const handleCull = useCallback((ids: string[]) => {
    setPhotos((prev) => prev.map((p) => (ids.includes(p.id) ? { ...p, culled: !p.culled } : p)))
    fetch('/ingest/photos/batch', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ ids, updates: { is_culled: true } }),
    })
  }, [csrfToken])

  const handleStar = useCallback((ids: string[], rating?: number) => {
    setPhotos((prev) =>
      prev.map((p) =>
        ids.includes(p.id)
          ? { ...p, starred: rating !== undefined ? rating > 0 : !p.starred, rating: rating ?? (p.starred ? 0 : 5) }
          : p,
      ),
    )
    fetch('/ingest/photos/batch', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ ids, updates: { rating: rating ?? 5 } }),
    })
  }, [csrfToken])

  const handleRotate = useCallback((ids: string[], direction: "cw" | "ccw") => {
    setPhotos((prev) =>
      prev.map((p) =>
        ids.includes(p.id) ? { ...p, rotation: (p.rotation + (direction === "cw" ? 90 : -90)) % 360 } : p,
      ),
    )
  }, [])

  const handleReorder = useCallback((fromId: string, toId: string) => {
    setPhotos((prev) => {
      const fromIndex = prev.findIndex((p) => p.id === fromId)
      const toIndex = prev.findIndex((p) => p.id === toId)
      const newPhotos = [...prev]
      const [moved] = newPhotos.splice(fromIndex, 1)
      newPhotos.splice(toIndex, 0, moved)
      const reordered = newPhotos.map((p, i) => ({ ...p, userOrder: i }))
      fetch('/ingest/photos/reorder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ order: reordered.map((p) => p.id) }),
      })
      return reordered
    })
  }, [csrfToken])

  const handleAddTags = useCallback((ids: string[], tags: Tag[], tagType: TagType = 'normal') => {
    // Optimistically update UI
    setPhotos((prev) => prev.map((p) => {
      if (!ids.includes(p.id)) return p
      const existingTags = p.tags
      const mergedTags = [...existingTags]
      tags.forEach(newTag => {
        if (!existingTags.some(t => t.name === newTag.name)) {
          mergedTags.push(newTag)
        }
      })
      return { ...p, tags: mergedTags }
    }))
    
    fetch('/ingest/photos/batch', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify({
        ids,
        updates: { 
          tags: tags.map(tag => ({ name: tag.name, tag_type: tagType }))
        },
      }),
    })
      .then(res => res.json())
      .then(data => {
        // Update with actual server response
        if (data.photos) {
          setPhotos(prev => prev.map(p => {
            const updated = data.photos.find((up: Photo) => up.id === p.id)
            return updated || p
          }))
        }
      })
      .catch(err => console.error('Failed to add tags:', err))
  }, [csrfToken])

  const handleRemoveTag = useCallback((ids: string[], tag: Tag) => {
    setPhotos((prev) => prev.map((p) => (ids.includes(p.id) ? { ...p, tags: p.tags.filter((t) => t.name !== tag.name) } : p)))
    
    // Remove tag from each photo individually
    ids.forEach(id => {
      const photo = photos.find(p => p.id === id)
      if (!photo) return
      
      const tagToRemove = photo.tags.find(t => t.name === tag.name)
      if (!tagToRemove) return
      
      fetch(`/ingest/photos/${id}/tags/${tagToRemove.id}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
        },
      }).catch(err => console.error('Failed to remove tag:', err))
    })
  }, [photos, csrfToken])

  const handleEnhance = useCallback(async (ids: string[]) => {
    if (ids.length === 0) return
    try {
      const response = await fetch('/ingest/enhance', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ ids }),
      })
      if (response.ok) {
        const data = await response.json()
        console.log('Enhancement queued:', data)
        // Photos will update automatically via preview polling when enhancement is complete
      }
    } catch (error) {
      console.error('Enhancement failed:', error)
    }
  }, [csrfToken])

  const handleAddPhotos = useCallback(async (files: FileList) => {
    setIsLoading(true)
    for (const file of Array.from(files)) {
      const formData = new FormData()
      formData.append('file', file)
      try {
        const response = await fetch('/ingest/upload', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrfToken },
          body: formData,
        })
        if (response.ok) {
          const data = await response.json()
          setPhotos((prev) => [...prev, data.photo])
        }
      } catch (error) {
        console.error('Upload failed:', error)
      }
    }
    setIsLoading(false)
  }, [csrfToken])

  const handleIngest = useCallback(async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return
    setIngestProgress(0)
    try {
      const response = await fetch('/ingest/ingest', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ ids }),
      })
      if (response.ok) {
        let progress = 0
        const interval = setInterval(() => {
          progress += Math.random() * 15
          if (progress >= 100) {
            setIngestProgress(null)
            setPhotos((prev) => prev.filter((p) => !ids.includes(p.id)))
            setSelectedIds(new Set())
            clearInterval(interval)
          } else {
            setIngestProgress(progress)
          }
        }, 200)
      }
    } catch (error) {
      console.error('Ingest failed:', error)
      setIngestProgress(null)
    }
  }, [selectedIds, csrfToken])

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Ignore if user is typing in an input field or contentEditable element
      if (
        e.target instanceof HTMLInputElement ||
        e.target instanceof HTMLTextAreaElement ||
        (e.target as HTMLElement).isContentEditable
      ) {
        return
      }

      const ids = Array.from(selectedIds)

      // Star ratings: 1-5
      if (e.key >= "1" && e.key <= "5") {
        e.preventDefault()
        const rating = parseInt(e.key)
        handleStar(ids, rating)
      }
      // Toggle cull
      else if (e.key === "c" || e.key === "C") {
        e.preventDefault()
        handleCull(ids)
      }
      // Toggle star (5-star rating)
      else if (e.key === "s" || e.key === "S") {
        e.preventDefault()
        handleStar(ids)
      }
      // Select all
      else if (e.key === "a" || e.key === "A") {
        e.preventDefault()
        handleSelectAll()
      }
      // De-select all
      else if (e.key === "d" || e.key === "D") {
        e.preventDefault()
        handleClearSelection()
      }
      // Enhance thumbnail quality
      else if (e.key === "e" || e.key === "E") {
        e.preventDefault()
        handleEnhance(ids)
      }
      // Open tags modal
      else if (e.key === "t" || e.key === "T") {
        e.preventDefault()
        setTagsModalOpen(true)
      }
      // Navigation: Previous photo
      else if (e.key === "ArrowLeft") {
        e.preventDefault()
        const idx = sortedPhotos.findIndex((p) => selectedIds.has(p.id))
        if (idx > 0) handleSelect(sortedPhotos[idx - 1].id, false, false)
      }
      // Navigation: Next photo
      else if (e.key === "ArrowRight") {
        e.preventDefault()
        const idx = sortedPhotos.findIndex((p) => selectedIds.has(p.id))
        if (idx < sortedPhotos.length - 1) handleSelect(sortedPhotos[idx + 1].id, false, false)
      }
    }
    window.addEventListener("keydown", handleKeyDown)
    return () => window.removeEventListener("keydown", handleKeyDown)
  }, [selectedIds, sortedPhotos, handleCull, handleStar, handleSelect, handleSelectAll, handleClearSelection, handleEnhance])

  const allTags = useMemo(() => {
    return availableTags
  }, [availableTags])

  const recentTags = useMemo(() => {
    const tagCounts = new Map<string, number>()
    photos.forEach((p) => p.tags.forEach((t) => tagCounts.set(t.name, (tagCounts.get(t.name) || 0) + 1)))
    return Array.from(tagCounts.entries())
      .sort((a, b) => b[1] - a[1])
      .slice(0, 10)
      .map(([tagName]) => availableTags.find(t => t.name === tagName) || { id: 0, name: tagName, slug: tagName, tag_type: 'normal' as TagType })
  }, [photos, availableTags])

  return (
    <div className="h-screen flex flex-col bg-background text-foreground">
      <TopActionBar
        totalCount={sortedPhotos.length}
        selectedCount={selectedIds.size}
        showCulled={showCulled}
        onShowCulledChange={setShowCulled}
        sortBy={sortBy}
        onSortChange={setSortBy}
        onAddPhotos={handleAddPhotos}
        onIngest={handleIngest}
        ingestProgress={ingestProgress}
      />
      <div className="flex-1 flex overflow-hidden">
        <ResizablePanelGroup direction="horizontal" className="flex-1">
          <ResizablePanel defaultSize={20} minSize={15} maxSize={30}>
            <ThumbnailBrowser
              photos={sortedPhotos}
              selectedIds={selectedIds}
              onSelect={handleSelect}
              onCull={handleCull}
              onStar={handleStar}
              onRotate={handleRotate}
              onReorder={handleReorder}
              onEnhance={handleEnhance}
              onSelectAll={handleSelectAll}
              onClearSelection={handleClearSelection}
              onOpenTagsModal={() => setTagsModalOpen(true)}
              isLoading={isLoading}
            />
          </ResizablePanel>
          <ResizableHandle withHandle />
          <ResizablePanel defaultSize={45} minSize={30}>
            <ImagePreview
              photo={activePhoto}
              selectedPhotos={selectedPhotos}
              onCull={handleCull}
              onStar={handleStar}
              onRotate={handleRotate}
            />
          </ResizablePanel>
          <ResizableHandle withHandle />
          <ResizablePanel defaultSize={35} minSize={25} maxSize={45}>
            <RightPanel
              photo={activePhoto}
              selectedIds={selectedIds}
              photos={sortedPhotos}
              filters={filters}
              onFiltersChange={setFilters}
              onSelectByFilter={handleSelectByFilter}
              cumulativeMode={cumulativeMode}
              onCumulativeModeChange={setCumulativeMode}
              isLoading={isLoading}
            />
          </ResizablePanel>
        </ResizablePanelGroup>
      </div>
      <TagsModal
        open={tagsModalOpen}
        onOpenChange={setTagsModalOpen}
        photo={activePhoto}
        selectedIds={selectedIds}
        allTags={allTags}
        recentTags={recentTags}
        onAddTags={handleAddTags}
        onRemoveTag={handleRemoveTag}
      />
    </div>
  )
}
