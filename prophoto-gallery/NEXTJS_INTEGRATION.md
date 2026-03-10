# Next.js Integration Guide

## Connecting Your v0 Gallery Designs to Laravel Backend

This guide shows how to connect your beautiful v0 Next.js gallery designs to the prophoto-galleries Laravel API.

---

## Architecture Overview

```
┌─────────────────────────────────┐
│   Next.js Frontend              │
│   (v0-Gallerie-Designs)        │
│   - Public gallery views        │
│   - Beautiful UI components     │
│   - Client-side interactions    │
└────────────┬────────────────────┘
             │
             │ HTTP/JSON API
             │ (Sanctum Auth)
             │
┌────────────▼────────────────────┐
│   Laravel Backend               │
│   - prophoto-access (RBAC)     │
│   - prophoto-galleries (API)   │
│   - Filament Admin Panel       │
└─────────────────────────────────┘
```

---

## Step 1: Laravel Setup

### Install Laravel Sanctum (for API authentication)

```bash
cd ~/Herd-Profoto/prophoto-access

# Install Sanctum if not already installed
composer require laravel/sanctum

# Publish Sanctum config
php artisan vendor:publish --provider="Laravel\Sanctum\ServiceProviderProvider"

# Run migrations
php artisan migrate
```

### Configure CORS for Next.js

Edit `config/cors.php`:

```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:3000',  // Next.js dev server
        'https://your-nextjs-domain.com', // Production domain
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

### Configure Sanctum

Edit `config/sanctum.php`:

```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
))),
```

### Update .env

```env
SESSION_DRIVER=cookie
SESSION_DOMAIN=localhost
SANCTUM_STATEFUL_DOMAINS=localhost:3000
```

---

## Step 2: Next.js API Client Setup

### Install Dependencies

```bash
cd ~/Herd-Profoto/v0-Gallerie-Designs

npm install axios
```

### Create API Client

Create `lib/api/client.ts`:

```typescript
import axios from 'axios'

const apiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  withCredentials: true, // Important for Sanctum
})

// Request interceptor for auth token
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response interceptor for errors
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Redirect to login or refresh token
      localStorage.removeItem('auth_token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default apiClient
```

### Create Auth Service

Create `lib/api/auth.ts`:

```typescript
import apiClient from './client'

export const authService = {
  // Get CSRF cookie before login
  async getCsrfCookie() {
    await apiClient.get('http://localhost:8000/sanctum/csrf-cookie')
  },

  // Login
  async login(email: string, password: string) {
    await this.getCsrfCookie()
    const response = await apiClient.post('/login', { email, password })
    const token = response.data.token
    localStorage.setItem('auth_token', token)
    return response.data
  },

  // Logout
  async logout() {
    await apiClient.post('/logout')
    localStorage.removeItem('auth_token')
  },

  // Get current user
  async me() {
    const response = await apiClient.get('/user')
    return response.data
  },
}
```

### Create Galleries API Service

Create `lib/api/galleries.ts`:

```typescript
import apiClient from './client'

export interface Gallery {
  id: number
  title: string
  slug: string
  description?: string
  cover_image_url?: string
  status: string
  images?: Image[]
  created_at: string
  updated_at: string
}

export interface Image {
  id: number
  gallery_id: number
  filename: string
  url: string
  thumbnail_url?: string
  title?: string
  description?: string
  is_marketing_approved: boolean
  ai_generated: boolean
  created_at: string
}

export const galleriesService = {
  // Get all galleries
  async getGalleries(page = 1) {
    const response = await apiClient.get(`/galleries?page=${page}`)
    return response.data
  },

  // Get single gallery by ID or slug
  async getGallery(identifier: string | number) {
    const response = await apiClient.get(`/galleries/${identifier}`)
    return response.data.data as Gallery
  },

  // Get gallery images
  async getGalleryImages(galleryId: number, page = 1) {
    const response = await apiClient.get(`/galleries/${galleryId}/images?page=${page}`)
    return response.data
  },

  // Create gallery
  async createGallery(data: Partial<Gallery>) {
    const response = await apiClient.post('/galleries', data)
    return response.data.data
  },

  // Upload images
  async uploadImages(galleryId: number, files: File[]) {
    const formData = new FormData()
    files.forEach((file, index) => {
      formData.append(`images[${index}]`, file)
    })

    const response = await apiClient.post(`/galleries/${galleryId}/images`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    return response.data
  },

  // Download image
  async downloadImage(galleryId: number, imageId: number) {
    const response = await apiClient.get(
      `/galleries/${galleryId}/images/${imageId}/download`,
      { responseType: 'blob' }
    )
    return response.data
  },

  // Rate image
  async rateImage(galleryId: number, imageId: number, rating: number) {
    const response = await apiClient.post(
      `/galleries/${galleryId}/images/${imageId}/rate`,
      { rating }
    )
    return response.data
  },

  // Approve image for marketing
  async approveImage(galleryId: number, imageId: number) {
    const response = await apiClient.post(
      `/galleries/${galleryId}/images/${imageId}/approve`
    )
    return response.data
  },
}
```

### Create Share Links API Service

Create `lib/api/shares.ts`:

```typescript
import apiClient from './client'

export interface ShareLink {
  id: number
  gallery_id: number
  share_token: string
  share_url: string
  has_password: boolean
  expires_at?: string
  max_views?: number
  view_count: number
  allow_downloads: boolean
  allow_comments: boolean
  is_valid: boolean
}

export const sharesService = {
  // Create share link
  async createShareLink(galleryId: number, data: {
    password?: string
    expires_at?: string
    max_views?: number
    allow_downloads?: boolean
    allow_comments?: boolean
  }) {
    const response = await apiClient.post(`/galleries/${galleryId}/shares`, data)
    return response.data.data as ShareLink
  },

  // Get share links for gallery
  async getShareLinks(galleryId: number) {
    const response = await apiClient.get(`/galleries/${galleryId}/shares`)
    return response.data
  },

  // Access gallery via share token
  async accessSharedGallery(token: string, password?: string) {
    const response = await apiClient.get(`/shares/${token}`, {
      params: { password },
    })
    return response.data.data
  },

  // Revoke share link
  async revokeShareLink(galleryId: number, shareId: number) {
    const response = await apiClient.delete(`/galleries/${galleryId}/shares/${shareId}`)
    return response.data
  },

  // Get share analytics
  async getShareAnalytics(galleryId: number, shareId: number) {
    const response = await apiClient.get(`/galleries/${galleryId}/shares/${shareId}/analytics`)
    return response.data
  },
}
```

---

## Step 3: Update Your v0 Components

### Update PortraitGallery to Use API

Edit `components/galleries/portrait-gallery.tsx`:

```tsx
"use client"

import { useState, useEffect } from "react"
import { galleriesService, Gallery, Image } from "@/lib/api/galleries"
import { ImageDetailModal } from "./image-detail-modal"
import { UniversalImageCard } from "./universal-image-card"
import { GalleryTools } from "./gallery-tools"

interface PortraitGalleryProps {
  galleryId: number
  userRole?: "studio_user" | "client_user" | "guest_user"
}

export function PortraitGallery({ galleryId, userRole = "guest_user" }: PortraitGalleryProps) {
  const [gallery, setGallery] = useState<Gallery | null>(null)
  const [images, setImages] = useState<Image[]>([])
  const [selectedImage, setSelectedImage] = useState<Image | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadGallery()
  }, [galleryId])

  const loadGallery = async () => {
    try {
      setLoading(true)
      const data = await galleriesService.getGallery(galleryId)
      setGallery(data)
      setImages(data.images || [])
    } catch (error) {
      console.error('Failed to load gallery:', error)
    } finally {
      setLoading(false)
    }
  }

  const handleUploadImages = async () => {
    // Implement file picker and upload
    const input = document.createElement('input')
    input.type = 'file'
    input.multiple = true
    input.accept = 'image/*'
    input.onchange = async (e) => {
      const files = Array.from((e.target as HTMLInputElement).files || [])
      try {
        await galleriesService.uploadImages(galleryId, files)
        await loadGallery() // Reload gallery
      } catch (error) {
        console.error('Upload failed:', error)
      }
    }
    input.click()
  }

  const handleDownload = async (imageId: string) => {
    try {
      const blob = await galleriesService.downloadImage(galleryId, Number(imageId))
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `image-${imageId}.jpg`
      a.click()
    } catch (error) {
      console.error('Download failed:', error)
    }
  }

  const handleRate = async (imageId: string, rating: number) => {
    try {
      await galleriesService.rateImage(galleryId, Number(imageId), rating)
    } catch (error) {
      console.error('Rating failed:', error)
    }
  }

  const handleApprove = async (imageId: string) => {
    try {
      await galleriesService.approveImage(galleryId, Number(imageId))
      await loadGallery()
    } catch (error) {
      console.error('Approval failed:', error)
    }
  }

  if (loading) {
    return <div className="min-h-screen bg-[#0d0d0f] flex items-center justify-center">
      <p className="text-[#f4efe9]">Loading gallery...</p>
    </div>
  }

  if (!gallery) {
    return <div className="min-h-screen bg-[#0d0d0f] flex items-center justify-center">
      <p className="text-[#f4efe9]">Gallery not found</p>
    </div>
  }

  return (
    <div className="min-h-screen bg-[#0d0d0f] relative overflow-hidden">
      {/* Keep your existing styling */}
      <header className="px-6 md:px-12 pt-20 pb-16">
        <div className="max-w-6xl mx-auto">
          <h1 className="font-sans font-light text-5xl md:text-7xl text-[#f4efe9]">
            {gallery.title}
          </h1>
          <p className="font-sans text-lg md:text-xl text-[#c7c3be] max-w-xl">
            {gallery.description}
          </p>
        </div>
      </header>

      <div className="max-w-6xl mx-auto px-6 md:px-12 pb-8">
        <GalleryTools
          canUploadImages={userRole === 'studio_user'}
          onUploadImages={handleUploadImages}
          variant="dark"
        />
      </div>

      <section className="px-6 md:px-12 pb-32 pt-16">
        <div className="max-w-6xl mx-auto">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {images.map((image) => (
              <div key={image.id}>
                <UniversalImageCard
                  src={image.url}
                  alt={image.title || image.filename}
                  onClick={() => setSelectedImage(image)}
                  className="aspect-[3/4] rounded-2xl"
                />
                <div className="mt-4">
                  <h3 className="font-sans text-[#f4efe9] text-lg">{image.title}</h3>
                  <p className="text-sm text-[#c7c3be]">{image.description}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <ImageDetailModal
        isOpen={!!selectedImage}
        onClose={() => setSelectedImage(null)}
        image={selectedImage as any}
        onApprove={handleApprove}
        onDownload={handleDownload}
        onRate={handleRate}
      />
    </div>
  )
}
```

---

## Step 4: Environment Variables

Create `.env.local` in your Next.js project:

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
NEXT_PUBLIC_APP_URL=http://localhost:3000
```

---

## Step 5: Create Gallery Pages

Create `app/galleries/[slug]/page.tsx`:

```tsx
import { PortraitGallery } from "@/components/galleries/portrait-gallery"

export default function GalleryPage({ params }: { params: { slug: string } }) {
  return <PortraitGallery galleryId={params.slug} />
}
```

---

## API Endpoints Reference

### Galleries

- `GET /api/galleries` - List all galleries
- `GET /api/galleries/{id}` - Get single gallery
- `POST /api/galleries` - Create gallery
- `PUT /api/galleries/{id}` - Update gallery
- `DELETE /api/galleries/{id}` - Delete gallery
- `GET /api/galleries/{id}/stats` - Get gallery statistics

### Images

- `GET /api/galleries/{id}/images` - List gallery images
- `POST /api/galleries/{id}/images` - Upload images
- `GET /api/galleries/{id}/images/{imageId}` - Get single image
- `GET /api/galleries/{id}/images/{imageId}/download` - Download image
- `POST /api/galleries/{id}/images/{imageId}/rate` - Rate image (1-5)
- `POST /api/galleries/{id}/images/{imageId}/approve` - Approve for marketing

### Share Links

- `GET /api/galleries/{id}/shares` - List share links
- `POST /api/galleries/{id}/shares` - Create share link
- `GET /api/shares/{token}` - Access via share token (public)
- `DELETE /api/galleries/{id}/shares/{shareId}` - Revoke share link
- `GET /api/galleries/{id}/shares/{shareId}/analytics` - Get share analytics

### Collections

- `GET /api/collections` - List collections
- `GET /api/collections/{id}` - Get collection
- `POST /api/collections` - Create collection
- `PUT /api/collections/{id}` - Update collection
- `DELETE /api/collections/{id}` - Delete collection
- `POST /api/collections/{id}/galleries` - Add galleries to collection
- `DELETE /api/collections/{id}/galleries` - Remove galleries from collection

---

## Permission-Based UI

Your v0 components already have permission props! Map them to Laravel permissions:

```tsx
const userPermissions = {
  studio_user: {
    canUploadImages: true,
    canGenerateAIPortraits: true,
    canShareGallery: true,
    canDownloadGallery: true,
  },
  client_user: {
    canUploadImages: false,
    canGenerateAIPortraits: false,
    canShareGallery: true,
    canDownloadGallery: true,
  },
  guest_user: {
    canUploadImages: false,
    canGenerateAIPortraits: false,
    canShareGallery: true,
    canDownloadGallery: true,
  },
}
```

---

## Testing

1. Start Laravel backend:
```bash
cd ~/Herd-Profoto/prophoto-access
php artisan serve
```

2. Start Next.js frontend:
```bash
cd ~/Herd-Profoto/v0-Gallerie-Designs
npm run dev
```

3. Test API:
```bash
curl http://localhost:8000/api/galleries \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

---

## Next Steps

1. Set up authentication in Next.js
2. Create login/register pages
3. Add loading states and error handling
4. Implement real-time updates with Laravel Echo (optional)
5. Add image optimization and lazy loading
6. Deploy to production

Your beautiful v0 designs now have a powerful Laravel backend! 🎉
