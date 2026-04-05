"use client"

import type { Photo, Filters } from "../types"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "./ui/tabs"
import { MetadataTab } from "./MetadataTab"
import { FiltersTab } from "./FiltersTab"
import { ChartsTab } from "./ChartsTab"

interface RightPanelProps {
  photo: Photo | null
  selectedIds: Set<string>
  photos: Photo[]
  filters: Filters
  onFiltersChange: (filters: Filters) => void
  onSelectByFilter: (ids: string[], additive: boolean) => void
  cumulativeMode: boolean
  onCumulativeModeChange: (mode: boolean) => void
  isLoading: boolean
}

export function RightPanel({
  photo,
  selectedIds,
  photos,
  filters,
  onFiltersChange,
  onSelectByFilter,
  cumulativeMode,
  onCumulativeModeChange,
  isLoading,
}: RightPanelProps) {
  return (
    <div className="h-full border-l border-border bg-card">
      <Tabs defaultValue="charts" className="h-full flex flex-col">
        <TabsList className="w-full rounded-none border-b border-border bg-transparent h-10 p-0">
          <TabsTrigger
            value="metadata"
            className="flex-1 rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent"
          >
            Metadata
          </TabsTrigger>
          <TabsTrigger
            value="filters"
            className="flex-1 rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent"
          >
            Filters
          </TabsTrigger>
          <TabsTrigger
            value="charts"
            className="flex-1 rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent"
          >
            Charts
          </TabsTrigger>
        </TabsList>

        <TabsContent value="metadata" className="flex-1 overflow-hidden mt-0">
          <MetadataTab photo={photo} />
        </TabsContent>

        <TabsContent value="filters" className="flex-1 overflow-hidden mt-0">
          <FiltersTab
            photos={photos}
            filters={filters}
            onFiltersChange={onFiltersChange}
          />
        </TabsContent>

        <TabsContent value="charts" className="flex-1 overflow-hidden mt-0">
          <ChartsTab
            photos={photos}
            selectedIds={selectedIds}
            onSelectByFilter={onSelectByFilter}
            cumulativeMode={cumulativeMode}
            onCumulativeModeChange={onCumulativeModeChange}
            isLoading={isLoading}
          />
        </TabsContent>
      </Tabs>
    </div>
  )
}
