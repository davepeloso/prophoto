"use client"

import type React from "react"
import { useMemo, useState, useCallback } from "react"
import type { Photo } from "../types"
import { ScrollArea } from "./ui/scroll-area"
import { Button } from "./ui/button"
import { Switch } from "./ui/switch"
import { Label } from "./ui/label"
import { Skeleton } from "./ui/skeleton"
import { BarChart } from "lucide-react"
import {
  BarChart as RechartsBarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Cell,
  ScatterChart,
  Scatter,
  ZAxis,
} from "recharts"

interface ChartsTabProps {
  photos: Photo[]
  selectedIds: Set<string>
  onSelectByFilter: (ids: string[], additive: boolean) => void
  cumulativeMode: boolean
  onCumulativeModeChange: (mode: boolean) => void
  isLoading: boolean
}

export function ChartsTab({
  photos,
  selectedIds,
  onSelectByFilter,
  cumulativeMode,
  onCumulativeModeChange,
  isLoading,
}: ChartsTabProps) {
  // ISO Distribution
  const isoData = useMemo(() => {
    const isoGroups = [100, 200, 400, 800, 1600, 3200, 6400]
    return isoGroups.map((iso) => {
      const matchingPhotos = photos.filter((p) => {
        if (iso === 6400) return p.iso >= 6400
        const nextIso = isoGroups[isoGroups.indexOf(iso) + 1] || Number.POSITIVE_INFINITY
        return p.iso >= iso && p.iso < nextIso
      })
      return {
        label: iso === 6400 ? "6400+" : iso.toString(),
        value: iso,
        count: matchingPhotos.length,
        ids: matchingPhotos.map((p) => p.id),
        selectedCount: matchingPhotos.filter((p) => selectedIds.has(p.id)).length,
      }
    })
  }, [photos, selectedIds])

  // Aperture Distribution
  const apertureData = useMemo(() => {
    const apertures = [1.4, 1.8, 2.8, 4, 5.6, 8, 11, 16]
    return apertures.map((ap) => {
      const matchingPhotos = photos.filter((p) => {
        if (ap === 16) return p.aperture >= 16
        const nextAp = apertures[apertures.indexOf(ap) + 1] || Number.POSITIVE_INFINITY
        return p.aperture >= ap && p.aperture < nextAp
      })
      return {
        label: `f/${ap}${ap === 16 ? "+" : ""}`,
        value: ap,
        count: matchingPhotos.length,
        ids: matchingPhotos.map((p) => p.id),
        selectedCount: matchingPhotos.filter((p) => selectedIds.has(p.id)).length,
      }
    })
  }, [photos, selectedIds])

  // Focal Length Distribution
  const focalLengthData = useMemo(() => {
    const ranges = [
      { label: "14-24mm", min: 14, max: 24 },
      { label: "24-35mm", min: 24, max: 35 },
      { label: "35-50mm", min: 35, max: 50 },
      { label: "50-85mm", min: 50, max: 85 },
      { label: "85-135mm", min: 85, max: 135 },
      { label: "135mm+", min: 135, max: Number.POSITIVE_INFINITY },
    ]
    return ranges.map((range) => {
      const matchingPhotos = photos.filter((p) => p.focalLength >= range.min && p.focalLength < range.max)
      return {
        label: range.label,
        count: matchingPhotos.length,
        ids: matchingPhotos.map((p) => p.id),
        selectedCount: matchingPhotos.filter((p) => selectedIds.has(p.id)).length,
      }
    })
  }, [photos, selectedIds])

  // Camera/Lens Combination
  const cameraLensData = useMemo(() => {
    const combos: Record<string, { count: number; ids: string[] }> = {}
    photos.forEach((p) => {
      const key = `${p.camera} + ${p.lens}`
      if (!combos[key]) combos[key] = { count: 0, ids: [] }
      combos[key].count++
      combos[key].ids.push(p.id)
    })
    return Object.entries(combos)
      .map(([label, data]) => ({
        label,
        count: data.count,
        ids: data.ids,
        selectedCount: data.ids.filter((id) => selectedIds.has(id)).length,
      }))
      .sort((a, b) => b.count - a.count)
      .slice(0, 5)
  }, [photos, selectedIds])

  // Timeline Data
  const timelineData = useMemo(() => {
    if (photos.length === 0) return []
    const times = photos.map((p) => new Date(p.dateTaken).getTime())
    const minTime = Math.min(...times)
    const maxTime = Math.max(...times)
    const bucketSize = (maxTime - minTime) / 12 || 600000

    const buckets: Record<number, { time: number; photos: Photo[] }> = {}
    photos.forEach((p) => {
      const time = new Date(p.dateTaken).getTime()
      const bucket = Math.floor((time - minTime) / bucketSize) * bucketSize + minTime
      if (!buckets[bucket]) buckets[bucket] = { time: bucket, photos: [] }
      buckets[bucket].photos.push(p)
    })

    return Object.values(buckets)
      .map((b) => ({
        time: new Date(b.time).toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" }),
        count: b.photos.length,
        ids: b.photos.map((p) => p.id),
        selectedCount: b.photos.filter((p) => selectedIds.has(p.id)).length,
      }))
      .sort((a, b) => a.time.localeCompare(b.time))
  }, [photos, selectedIds])

  const handleBarClick = useCallback(
    (data: { ids: string[] }, e?: React.MouseEvent) => {
      const additive = e?.metaKey || e?.ctrlKey || cumulativeMode
      onSelectByFilter(data.ids, additive)
    },
    [onSelectByFilter, cumulativeMode],
  )

  const handleClearSelection = useCallback(() => {
    onSelectByFilter([], false)
  }, [onSelectByFilter])

  const getBarFill = (selectedCount: number, totalCount: number) => {
    if (selectedCount === totalCount && totalCount > 0) return "hsl(var(--primary))"
    if (selectedCount > 0) return "hsl(var(--primary) / 0.5)"
    return "hsl(var(--muted-foreground) / 0.3)"
  }

  if (isLoading) {
    return (
      <div className="p-4 space-y-6">
        <Skeleton className="h-[150px] w-full" />
        <Skeleton className="h-[150px] w-full" />
        <Skeleton className="h-[150px] w-full" />
      </div>
    )
  }

  if (photos.length === 0) {
    return (
      <div className="h-full flex items-center justify-center text-muted-foreground">
        <div className="text-center">
          <BarChart className="h-12 w-12 mx-auto mb-3 opacity-40" />
          <p className="text-sm">Upload photos to see distribution</p>
        </div>
      </div>
    )
  }

  return (
    <ScrollArea className="h-full">
      <div className="p-4 space-y-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Switch id="cumulative" checked={cumulativeMode} onCheckedChange={onCumulativeModeChange} />
            <Label htmlFor="cumulative" className="text-xs">Cumulative Selection</Label>
          </div>
          <Button variant="ghost" size="sm" onClick={handleClearSelection} className="text-xs">
            Clear Chart Selection
          </Button>
        </div>

        <div className="flex items-center gap-4 text-xs text-muted-foreground">
          <div className="flex items-center gap-1.5">
            <div className="w-3 h-3 rounded bg-primary" />
            <span>Selected</span>
          </div>
          <div className="flex items-center gap-1.5">
            <div className="w-3 h-3 rounded bg-muted-foreground/30" />
            <span>Unselected</span>
          </div>
        </div>

        <ChartSection title="ISO Distribution">
          <ResponsiveContainer width="100%" height={140}>
            <RechartsBarChart data={isoData} margin={{ top: 5, right: 5, bottom: 20, left: 5 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" vertical={false} />
              <XAxis dataKey="label" tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} axisLine={{ stroke: "hsl(var(--border))" }} />
              <YAxis tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} axisLine={{ stroke: "hsl(var(--border))" }} width={25} />
              <Tooltip content={({ payload }) => {
                if (!payload?.[0]) return null
                const data = payload[0].payload
                return (
                  <div className="bg-popover border border-border rounded-md px-3 py-2 text-xs shadow-lg">
                    <div className="font-medium">ISO {data.label}</div>
                    <div className="text-muted-foreground">{data.count} photos</div>
                  </div>
                )
              }} />
              <Bar dataKey="count" radius={[4, 4, 0, 0]} cursor="pointer" onClick={(data, _, e) => handleBarClick(data, e as unknown as React.MouseEvent)}>
                {isoData.map((entry, index) => (
                  <Cell key={index} fill={getBarFill(entry.selectedCount, entry.count)} className="transition-colors duration-150 hover:brightness-110" />
                ))}
              </Bar>
            </RechartsBarChart>
          </ResponsiveContainer>
        </ChartSection>

        <ChartSection title="Aperture Distribution">
          <ResponsiveContainer width="100%" height={140}>
            <RechartsBarChart data={apertureData} margin={{ top: 5, right: 5, bottom: 20, left: 5 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" vertical={false} />
              <XAxis dataKey="label" tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} axisLine={{ stroke: "hsl(var(--border))" }} />
              <YAxis tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} axisLine={{ stroke: "hsl(var(--border))" }} width={25} />
              <Tooltip content={({ payload }) => {
                if (!payload?.[0]) return null
                const data = payload[0].payload
                return (
                  <div className="bg-popover border border-border rounded-md px-3 py-2 text-xs shadow-lg">
                    <div className="font-medium">{data.label}</div>
                    <div className="text-muted-foreground">{data.count} photos</div>
                  </div>
                )
              }} />
              <Bar dataKey="count" radius={[4, 4, 0, 0]} cursor="pointer" onClick={(data, _, e) => handleBarClick(data, e as unknown as React.MouseEvent)}>
                {apertureData.map((entry, index) => (
                  <Cell key={index} fill={getBarFill(entry.selectedCount, entry.count)} className="transition-colors duration-150 hover:brightness-110" />
                ))}
              </Bar>
            </RechartsBarChart>
          </ResponsiveContainer>
        </ChartSection>

        <ChartSection title="Focal Length Distribution">
          <ResponsiveContainer width="100%" height={140}>
            <RechartsBarChart data={focalLengthData} margin={{ top: 5, right: 5, bottom: 20, left: 5 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" vertical={false} />
              <XAxis dataKey="label" tick={{ fontSize: 9, fill: "hsl(var(--muted-foreground))" }} axisLine={{ stroke: "hsl(var(--border))" }} />
              <YAxis tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} axisLine={{ stroke: "hsl(var(--border))" }} width={25} />
              <Tooltip content={({ payload }) => {
                if (!payload?.[0]) return null
                const data = payload[0].payload
                return (
                  <div className="bg-popover border border-border rounded-md px-3 py-2 text-xs shadow-lg">
                    <div className="font-medium">{data.label}</div>
                    <div className="text-muted-foreground">{data.count} photos</div>
                  </div>
                )
              }} />
              <Bar dataKey="count" radius={[4, 4, 0, 0]} cursor="pointer" onClick={(data, _, e) => handleBarClick(data, e as unknown as React.MouseEvent)}>
                {focalLengthData.map((entry, index) => (
                  <Cell key={index} fill={getBarFill(entry.selectedCount, entry.count)} className="transition-colors duration-150 hover:brightness-110" />
                ))}
              </Bar>
            </RechartsBarChart>
          </ResponsiveContainer>
        </ChartSection>

        <ChartSection title="Camera + Lens Combinations">
          <ResponsiveContainer width="100%" height={160}>
            <RechartsBarChart data={cameraLensData} layout="vertical" margin={{ top: 5, right: 5, bottom: 5, left: 5 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" horizontal={false} />
              <XAxis type="number" tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} axisLine={{ stroke: "hsl(var(--border))" }} />
              <YAxis type="category" dataKey="label" tick={{ fontSize: 8, fill: "hsl(var(--muted-foreground))" }} axisLine={{ stroke: "hsl(var(--border))" }} width={120} />
              <Tooltip content={({ payload }) => {
                if (!payload?.[0]) return null
                const data = payload[0].payload
                return (
                  <div className="bg-popover border border-border rounded-md px-3 py-2 text-xs shadow-lg">
                    <div className="font-medium">{data.label}</div>
                    <div className="text-muted-foreground">{data.count} photos</div>
                  </div>
                )
              }} />
              <Bar dataKey="count" radius={[0, 4, 4, 0]} cursor="pointer" onClick={(data, _, e) => handleBarClick(data, e as unknown as React.MouseEvent)}>
                {cameraLensData.map((entry, index) => (
                  <Cell key={index} fill={getBarFill(entry.selectedCount, entry.count)} className="transition-colors duration-150 hover:brightness-110" />
                ))}
              </Bar>
            </RechartsBarChart>
          </ResponsiveContainer>
        </ChartSection>

        <ChartSection title="Timeline">
          <ResponsiveContainer width="100%" height={140}>
            <RechartsBarChart data={timelineData} margin={{ top: 5, right: 5, bottom: 20, left: 5 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" vertical={false} />
              <XAxis dataKey="time" tick={{ fontSize: 9, fill: "hsl(var(--muted-foreground))" }} axisLine={{ stroke: "hsl(var(--border))" }} />
              <YAxis tick={{ fontSize: 10, fill: "hsl(var(--muted-foreground))" }} axisLine={{ stroke: "hsl(var(--border))" }} width={25} />
              <Tooltip content={({ payload }) => {
                if (!payload?.[0]) return null
                const data = payload[0].payload
                return (
                  <div className="bg-popover border border-border rounded-md px-3 py-2 text-xs shadow-lg">
                    <div className="font-medium">{data.time}</div>
                    <div className="text-muted-foreground">{data.count} photos</div>
                  </div>
                )
              }} />
              <Bar dataKey="count" radius={[4, 4, 0, 0]} cursor="pointer" onClick={(data, _, e) => handleBarClick(data, e as unknown as React.MouseEvent)}>
                {timelineData.map((entry, index) => (
                  <Cell key={index} fill={getBarFill(entry.selectedCount, entry.count)} className="transition-colors duration-150 hover:brightness-110" />
                ))}
              </Bar>
            </RechartsBarChart>
          </ResponsiveContainer>
        </ChartSection>
      </div>
    </ScrollArea>
  )
}

function ChartSection({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="space-y-2">
      <h4 className="text-xs font-medium text-muted-foreground uppercase tracking-wide">{title}</h4>
      <div className="bg-muted/30 rounded-lg p-2">{children}</div>
    </div>
  )
}
