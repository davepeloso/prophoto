import { useState } from "react"
import { Button } from "./ui/button"
import { Badge } from "./ui/badge"
import { X, Plus, FolderOpen, FileText, Tag as TagIcon } from "lucide-react"
import type { Photo, Tag, TagType } from "../types"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "./ui/dialog"
import { Label } from "./ui/label"
import { RadioGroup, RadioGroupItem } from "./ui/radio-group"

interface TagsModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  photo: Photo | null
  selectedIds: Set<string>
  allTags: Tag[]
  recentTags: Tag[]
  onAddTags: (ids: string[], tags: Tag[], tagType?: TagType) => void
  onRemoveTag: (ids: string[], tag: Tag) => void
}

export function TagsModal({
  open,
  onOpenChange,
  photo,
  selectedIds,
  allTags,
  recentTags,
  onAddTags,
  onRemoveTag,
}: TagsModalProps) {
  const [inputValue, setInputValue] = useState("")
  const [tagType, setTagType] = useState<TagType>("normal")

  const handleAddTag = () => {
    if (!inputValue.trim() || selectedIds.size === 0) return
    const newTag: Tag = {
      id: Date.now(),
      name: inputValue.trim(),
      slug: inputValue.trim().toLowerCase().replace(/\s+/g, '-'),
      tag_type: tagType
    }
    onAddTags(Array.from(selectedIds), [newTag], tagType)
    setInputValue("")
    setTagType("normal")
  }

  const handleQuickTag = (tag: Tag) => {
    if (selectedIds.size === 0) return
    onAddTags(Array.from(selectedIds), [tag], tag.tag_type)
  }

  const appliedTags = photo?.tags || []

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Add Tags</DialogTitle>
        </DialogHeader>
        <div className="flex flex-col gap-4 py-4">
          {/* Add Tags Section */}
          <div>
            <h3 className="text-sm font-medium mb-3">Add Tags</h3>
            
            {/* Tag Type Selection */}
            <div className="mb-3">
              <Label className="text-xs text-muted-foreground mb-2 block">Tag Type</Label>
              <RadioGroup value={tagType} onValueChange={(value) => setTagType(value as TagType)} className="flex gap-4">
                <div className="flex items-center space-x-2">
                  <RadioGroupItem value="normal" id="type-normal" />
                  <Label htmlFor="type-normal" className="flex items-center gap-1.5 cursor-pointer text-sm font-normal">
                    <TagIcon className="h-3.5 w-3.5" />
                    Normal
                  </Label>
                </div>
                <div className="flex items-center space-x-2">
                  <RadioGroupItem value="project" id="type-project" />
                  <Label htmlFor="type-project" className="flex items-center gap-1.5 cursor-pointer text-sm font-normal">
                    <FolderOpen className="h-3.5 w-3.5 text-blue-500" />
                    Project
                  </Label>
                </div>
                <div className="flex items-center space-x-2">
                  <RadioGroupItem value="filename" id="type-filename" />
                  <Label htmlFor="type-filename" className="flex items-center gap-1.5 cursor-pointer text-sm font-normal">
                    <FileText className="h-3.5 w-3.5 text-green-500" />
                    Filename
                  </Label>
                </div>
              </RadioGroup>
              {tagType === "project" && (
                <p className="text-xs text-muted-foreground mt-1.5">
                  Used in file path (e.g., &ldquo;123-Main-Street&rdquo;)
                </p>
              )}
              {tagType === "filename" && (
                <p className="text-xs text-muted-foreground mt-1.5">
                  Used in filename (e.g., &ldquo;Living-Room&rdquo;)
                </p>
              )}
            </div>

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
                  <Badge key={tag.id} variant="secondary" className="gap-1">
                    {tag.name}
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
                    key={tag.id}
                    variant="outline"
                    size="sm"
                    onClick={() => handleQuickTag(tag)}
                    disabled={selectedIds.size === 0}
                  >
                    {tag.name}
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
                    key={tag.id}
                    variant="ghost"
                    size="sm"
                    onClick={() => handleQuickTag(tag)}
                    disabled={selectedIds.size === 0}
                  >
                    {tag.name}
                  </Button>
                ))}
              </div>
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
