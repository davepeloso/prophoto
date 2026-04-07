export type TagType = 'normal' | 'project' | 'filename'

export interface Tag {
  id: number
  name: string
  slug: string
  color?: string
  tag_type: TagType
}

export interface Photo {
  id: string
  filename: string
  thumbnail: string | null
  fullSize?: string
  previewStatus: 'pending' | 'processing' | 'ready' | 'failed'
  previewReady: boolean
  dateTaken: string
  camera: string
  lens: string
  aperture: number
  shutterSpeed: string
  iso: number
  focalLength: number
  dimensions: string
  fileSize: string
  fileType: "RAW" | "JPG" | "TIFF" | string
  tags: Tag[]
  starred: boolean
  culled: boolean
  rating: number
  rotation: number
  userOrder: number
  gps?: {
    lat: number
    lng: number
    location: string
  } | null
}

export interface Filters {
  cameras: string[]
  dateRange: [Date, Date] | null
  apertureRange: [number, number] | null
  isoRange: [number, number] | null
  tags: Tag[]
}
