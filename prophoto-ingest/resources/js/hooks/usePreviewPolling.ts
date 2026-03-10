import { useState, useEffect, useCallback, useRef } from 'react'
import type { Photo } from '../types'

interface UsePreviewPollingOptions {
  photos: Photo[]
  onPhotosUpdate: (updates: Partial<Photo>[]) => void
  pollInterval?: number // ms
  enabled?: boolean
}

export function usePreviewPolling({
  photos,
  onPhotosUpdate,
  pollInterval = 2000,
  enabled = true,
}: UsePreviewPollingOptions) {
  const [isPolling, setIsPolling] = useState(false)
  const timeoutRef = useRef<NodeJS.Timeout | null>(null)
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

  const pendingIds = photos
    .filter(p => p.previewStatus === 'pending' || p.previewStatus === 'processing')
    .map(p => p.id)

  const poll = useCallback(async () => {
    if (pendingIds.length === 0) {
      setIsPolling(false)
      return
    }

    try {
      const response = await fetch('/ingest/preview-status', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ ids: pendingIds }),
      })

      if (response.ok) {
        const data = await response.json()

        // Find photos that have changed
        const updates = data.photos
          .filter((p: any) => p.previewStatus === 'ready' || p.previewStatus === 'failed')
          .map((p: any) => ({
            id: p.id,
            thumbnail: p.thumbnailUrl,
            fullSize: p.previewUrl,
            previewStatus: p.previewStatus,
            previewReady: p.previewReady,
          }))

        if (updates.length > 0) {
          onPhotosUpdate(updates)
        }
      }
    } catch (error) {
      console.error('Preview polling failed:', error)
    }

    // Schedule next poll if there are still pending photos
    if (pendingIds.length > 0 && enabled) {
      timeoutRef.current = setTimeout(poll, pollInterval)
    }
  }, [pendingIds, pollInterval, enabled, csrfToken, onPhotosUpdate])

  // Start/stop polling based on pending photos
  useEffect(() => {
    if (enabled && pendingIds.length > 0 && !isPolling) {
      setIsPolling(true)
      // Initial delay before first poll
      timeoutRef.current = setTimeout(poll, 1000)
    }

    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current)
      }
    }
  }, [enabled, pendingIds.length, isPolling, poll])

  return {
    isPolling,
    pendingCount: pendingIds.length,
  }
}
