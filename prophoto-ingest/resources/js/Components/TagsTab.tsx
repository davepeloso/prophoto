import { useState } from "react"
import { Button } from "./ui/button"
import { Badge } from "./ui/badge"
import { X, Plus } from "lucide-react"
import type { Photo } from "../types"

interface TagsTabProps {
    photo: Photo | null
    selectedIds: Set<string>
    allTags: string[]
    recentTags: string[]
    onAddTags: (ids: string[], tags: string[]) => void
    onRemoveTag: (ids: string[], tag: string) => void
}

export function TagsTab({
                            photo,
                            selectedIds,
                            allTags,
                            recentTags,
                            onAddTags,
                            onRemoveTag,
                        }: TagsTabProps) {
    const [inputValue, setInputValue] = useState("")

    const handleAddTag = () => {
        if (!inputValue.trim() || selectedIds.size === 0) return
        onAddTags(Array.from(selectedIds), [inputValue.trim()])
        setInputValue("")
    }

    const handleQuickTag = (tag: string) => {
        if (selectedIds.size === 0) return
        onAddTags(Array.from(selectedIds), [tag])
    }

    const appliedTags = photo?.tags || []

    return (
        <div className="h-full flex flex-col p-4 gap-4 overflow-y-auto">
            {/* Add Tags Section */}
            <div>
                <h3 className="text-sm font-medium mb-2">Add Tags</h3>
                <div className="flex gap-2">
                    <input
                        type="text"
                        value={inputValue}
                        onChange={(e) => setInputValue(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === "Enter") handleAddTag()
                        }}
                        placeholder="Add a tag..."
                        disabled={selectedIds.size === 0}
                        className="flex-1 h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <Button
                        onClick={handleAddTag}
                        disabled={!inputValue.trim() || selectedIds.size === 0}
                        size="sm"
                    >
                        <Plus className="h-4 w-4" />
                    </Button>
                </div>
                {selectedIds.size === 0 && (
                    <p className="text-xs text-muted-foreground mt-2">
                        Select photos to add tags
                    </p>
                )}
            </div>

            {/* Applied Tags Section */}
            {appliedTags.length > 0 && (
                <div>
                    <h3 className="text-sm font-medium mb-2">Applied Tags</h3>
                    <div className="flex flex-wrap gap-2">
                        {appliedTags.map((tag) => (
                            <Badge key={tag} variant="secondary" className="gap-1">
                                {tag}
                                <button
                                    onClick={() => onRemoveTag(Array.from(selectedIds), tag)}
                                    className="ml-1 hover:text-destructive"
                                    disabled={selectedIds.size === 0}
                                >
                                    <X className="h-3 w-3" />
                                </button>
                            </Badge>
                        ))}
                    </div>
                </div>
            )}

            {/* Quick Tags Section */}
            {recentTags.length > 0 && (
                <div>
                    <h3 className="text-sm font-medium mb-2">Quick Tags</h3>
                    <div className="flex flex-wrap gap-2">
                        {recentTags.map((tag) => (
                            <Button
                                key={tag}
                                variant="outline"
                                size="sm"
                                onClick={() => handleQuickTag(tag)}
                                disabled={selectedIds.size === 0}
                            >
                                {tag}
                            </Button>
                        ))}
                    </div>
                </div>
            )}

            {/* All Available Tags */}
            {allTags.length > 0 && (
                <div>
                    <h3 className="text-sm font-medium mb-2">All Tags</h3>
                    <div className="flex flex-wrap gap-2">
                        {allTags.slice(0, 20).map((tag) => (
                            <Button
                                key={tag}
                                variant="ghost"
                                size="sm"
                                onClick={() => handleQuickTag(tag)}
                                disabled={selectedIds.size === 0}
                            >
                                {tag}
                            </Button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    )
}