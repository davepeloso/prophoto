# Claude Context for prophoto Ingest

This document provides essential context for Claude AI when working with the prophoto Ingest package - a professional photo ingestion and organization tool for Laravel.

## Project Overview

**prophoto Ingest** is an Adobe Bridge-style media management package for Laravel that provides intelligent metadata extraction, interactive culling, and powerful tagging capabilities.

### Core Architecture

- **Laravel Package**: PSR-4 autoloaded under `prophoto\Ingest\`
- **Queue-Based Processing**: Three-tier job system for background image processing
- **ExifTool Integration**: External Perl binary for comprehensive metadata extraction
- **Image Processing**: Intervention Image v3 with ImageMagick/GD fallbacks
- **Inertia.js Frontend**: React-based UI with real-time status updates

## Technology Stack

### Backend Dependencies

- **PHP**: 8.1+ / 8.2+
- **Laravel**: 11.0+ / 12.0+
- **Intervention Image**: v3.0+ (ImageMagick/GD drivers)
- **Inertia.js**: v2.0+ for SPA-style frontend
- **ExifTool**: External Perl executable (required dependency)

### Key Services

- `ExifToolService` - Metadata extraction and preview generation
- `MetadataExtractor` - High-level metadata processing with fallbacks
- `IngestProcessor` - Final image processing and storage
- `IngestSettingsService` - Configuration management

## Job System Architecture

The package uses a sophisticated three-tier job processing system:

### 1. ProcessPreviewJob (`ingest-preview` queue)

- **Purpose**: Generate high-quality preview images and enhanced thumbnails
- **Priority**: Medium
- **Retries**: 3 with [10, 30, 60] second backoff
- **Outputs**: 
  - Preview image (~2048px) at `previews/{uuid}.jpg`
  - Enhanced thumbnail (~400px) at `thumbs/{uuid}.jpg`

### 2. ProcessImageIngestJob (`ingest` queue)

- **Purpose**: Final image processing and permanent storage
- **Priority**: High
- **Retries**: 3 with [10, 30, 60] second backoff, 30-minute window
- **Outputs**: Permanent `Image` record and file in `final_disk` location

### 3. EnhancePreviewJob (`ingest-enhance` queue)

- **Purpose**: User-triggered preview quality enhancement
- **Priority**: Low
- **Retries**: 2 with [5, 15] second backoff
- **Outputs**: Enhanced preview with updated dimensions

## Data Models

### ProxyImage (Temporary)

- Stores temporary metadata and processing status
- Tracks preview generation progress (`preview_status`)
- Maintains error information for failed jobs
- Cleaned up after successful final ingest

### Image (Permanent)

- Normalized metadata in application schema
- Permanent file references and associations
- Tag relationships and metadata

### Tag & IngestSetting

- Tagging system for photo organization
- Configurable schema patterns and settings

## File Storage Structure

### Temporary Storage (`ingest-temp/`)

```
ingest-temp/
├── {uuid}.ext              # Original uploaded file
├── thumbs/{uuid}.jpg       # 400x400 thumbnail
└── previews/{uuid}.jpg     # 2048px preview
```

### Permanent Storage (Configurable)

```
{final_path}/
└── {schema_pattern}/
    └── {final_filename}    # Original file moved to final location
```

## Key Processing Flows

### Upload Flow

1. HTTP upload → `IngestController@upload`
2. Fast metadata extraction (ExifTool `-fast2` mode)
3. Embedded thumbnail extraction
4. ProxyImage creation with `preview_status='pending'`
5. `ProcessPreviewJob` dispatch

### Preview Generation

1. Load ProxyImage from database
2. Mark status as `processing`
3. Extract embedded preview (primary fallback)
4. Generate from source using Intervention Image (secondary)
5. Keep existing thumbnail (tertiary fallback)
6. Update proxy with preview paths

### Final Ingest

1. User confirmation via HTTP POST
2. Batch job dispatch for selected images
3. File movement to permanent storage
4. Metadata normalization
5. Permanent Image record creation
6. Temporary file cleanup

## Error Handling & Fallbacks

### Multi-Layer Fallback Chain

1. **ExifTool** → PHP `exif_read_data()`
2. **ImageMagick** → GD extension  
3. **Embedded previews** → Generated from source
4. **Enhanced thumbnails** → Basic thumbnails

### Retry Logic

- Exponential backoff with configurable intervals
- Error message preservation in database
- Proxy record preservation for manual retry
- Graceful degradation (lower quality vs. complete failure)

## Configuration Points

### Performance Tuning

- ExifTool speed modes: `fast`, `fast2`, `full`
- Preview size limits (8MB default)
- Quality settings: Thumbnail (80%), Preview (85%), Final (95%)
- Queue timeouts and memory limits

### Storage Configuration

- Configurable disks for temporary vs. final storage
- Schema-based path/filename patterns
- Polymorphic association support

## Queue Management

### Worker Commands

```bash
# Start all queue workers
php artisan queue:work

# Start specific queue workers
php artisan queue:work --queue=ingest
php artisan queue:work --queue=ingest-preview
php artisan queue:work --queue=ingest-enhance

# Production with supervisor
php artisan queue:work --daemon --sleep=3 --tries=3
```

### Monitoring

```bash
# Monitor queue status
php artisan queue:monitor ingest ingest-preview ingest-enhance

# Check failed jobs
php artisan queue:failed

# View job logs
tail -f storage/logs/laravel.log | grep -i "queue\|job"
```

## Development Guidelines

### Testing

- Use `QUEUE_CONNECTION=sync` for synchronous testing
- Single job debugging: `php artisan queue:work --once`
- Pest testing framework with Laravel Testbench

### Code Style

- Laravel Pint for code formatting
- PHPStan for static analysis
- PSR-4 autoloading under `prophoto\Ingest\`

### Build Scripts

```bash
# Quick rebuild (backend changes only)
composer run rebuild:quick

# Standard rebuild (includes frontend)
composer run rebuild

# Full rebuild (clean install)
composer run rebuild:full
```

## External Dependencies

### ExifTool Requirements

- External Perl executable must be installed
- Configured via `EXIFTOOL_BINARY` environment variable
- Health checks and timeout handling (30s default)
- Supports 600+ file formats including RAW formats

### Image Processing

- ImageMagick preferred for RAW support
- GD extension as fallback
- Intervention Image v3 abstraction layer

## Security Considerations

- File type validation and sanitization
- Path traversal protection
- Queue job security with proper permissions
- Rate limiting for upload endpoints
- Secure temporary file handling

## Troubleshooting

### Common Issues

- ExifTool binary not found or permissions
- Queue workers not running
- Insufficient memory for large image processing
- Storage disk configuration errors

### Debug Commands

```bash
# Check ExifTool installation
php artisan ingest:exiftool-doctor

# Clear stuck jobs
php artisan queue:clear --queue=ingest

# Restart workers
php artisan queue:restart
```

## File Structure Reference

```
src/
├── Console/Commands/          # Artisan commands
├── Http/Controllers/          # HTTP endpoints
├── Jobs/                      # Queue job classes
├── Models/                    # Eloquent models
├── Services/                  # Business logic services
└── IngestServiceProvider.php  # Laravel service provider

config/                       # Package configuration
database/migrations/          # Database schema
resources/                   # Frontend assets (React/Vue)
routes/                      # API routes
tests/                       # Test suite
```

This context should provide Claude with comprehensive understanding of the prophoto Ingest package architecture, processing flows, and development patterns.
