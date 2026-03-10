# ProPhoto Dependencies

## System Dependencies

### Required Software

| Tool | Version | Purpose | Installation |
|------|---------|---------|--------------|
| PHP | 8.2+ | Runtime | `brew install php@8.2` (macOS)<br>`apt install php8.2` (Ubuntu) |
| Composer | 2.x | PHP dependency management | https://getcomposer.org/download/ |
| Node.js | 18+ | Frontend build tools | `brew install node` (macOS)<br>`apt install nodejs npm` (Ubuntu) |
| ExifTool | 12.0+ | Metadata extraction | `brew install exiftool` (macOS)<br>`apt install libimage-exiftool-perl` (Ubuntu) |

### Optional Tools

| Tool | Purpose | Installation |
|------|---------|--------------|
| Redis | Queue and cache backend | `brew install redis` (macOS)<br>`apt install redis-server` (Ubuntu) |
| MySQL/PostgreSQL | Production database | See database-specific guides |

### Verification

Check all dependencies at once:
```bash
./scripts/prophoto doctor
```

Or manually:
```bash
php --version        # Should show 8.2+
composer --version   # Should show 2.x
node --version       # Should show v18+
exiftool -ver        # Should show version number
```

## PHP Extensions

### Required Extensions

These are typically included with PHP but verify they're enabled:

- `mbstring` - Multibyte string handling
- `xml` - XML processing
- `pdo` - Database abstraction
- `pdo_sqlite` - SQLite support (for testing)
- `pdo_mysql` - MySQL support (for production)
- `curl` - HTTP requests
- `zip` - Archive handling
- `gd` - Image processing
- `exif` - EXIF metadata reading

### Check Installed Extensions
```bash
php -m
```

### Install Missing Extensions (Ubuntu)
```bash
sudo apt install php8.2-mbstring php8.2-xml php8.2-curl \
                 php8.2-zip php8.2-gd php8.2-mysql
```

## Composer Dependencies

### Workspace-Level (Sandbox)

Defined in `sandbox/composer.json`:

| Package | Version | Purpose |
|---------|---------|---------|
| laravel/framework | ^12.0 | Laravel framework |
| inertiajs/inertia-laravel | ^2.0 | SPA framework |
| laravel/fortify | ^1.30 | Authentication |
| filament/filament | ^4.4 | Admin panel |
| prophoto/contracts | @dev | Shared contracts |
| prophoto/ingest | @dev | Ingest package |
| prophoto/galleries | @dev | Galleries package |
| prophoto/access | @dev | Access control package |
| prophoto/debug | @dev | Debug utilities package |

### Package-Level

#### prophoto-contracts
```json
{
  "require": {
    "php": "^8.2"
  }
}
```
**No external dependencies** - this is intentional to keep contracts lightweight and stable.

#### prophoto-ingest
```json
{
  "require": {
    "php": "^8.2",
    "illuminate/support": "^11.0|^12.0",
    "intervention/image": "^3.0",
    "inertiajs/inertia-laravel": "^2.0"
  }
}
```

| Package | Purpose |
|---------|---------|
| intervention/image | Image manipulation and derivative generation |
| inertia-laravel | SPA integration for UI |

#### prophoto-galleries
```json
{
  "require": {
    "php": "^8.2",
    "illuminate/support": "^11.0|^12.0",
    "prophoto/access": "*"
  }
}
```

Depends on `prophoto-access` for authorization.

#### prophoto-access
```json
{
  "require": {
    "php": "^8.2",
    "laravel/framework": "^11.0|^12.0",
    "spatie/laravel-permission": "^6.0"
  }
}
```

| Package | Purpose |
|---------|---------|
| spatie/laravel-permission | RBAC foundation |

#### prophoto-debug
```json
{
  "require": {
    "php": "^8.2",
    "illuminate/support": "^11.0|^12.0",
    "illuminate/database": "^11.0|^12.0",
    "illuminate/events": "^11.0|^12.0"
  }
}
```

## Node/NPM Dependencies

### Sandbox Frontend

Defined in `sandbox/package.json`:

| Package | Purpose |
|---------|---------|
| @inertiajs/react | Inertia.js React adapter |
| react | React framework |
| react-dom | React DOM rendering |
| @vitejs/plugin-react | Vite React plugin |
| tailwindcss | CSS framework |
| autoprefixer | CSS post-processor |
| postcss | CSS transformation |

### Package-Level (Example: prophoto-ingest)

```json
{
  "devDependencies": {
    "vite": "^5.0",
    "@vitejs/plugin-react": "^4.0",
    "react": "^18.0",
    "tailwindcss": "^3.0"
  }
}
```

Each package with frontend assets has its own `package.json`.

## External Services

### ExifTool (Critical Dependency)

**Purpose**: Extracts metadata from photos (EXIF, IPTC, XMP)

**Used by**: `prophoto-ingest` package

**Why External**: ExifTool is a Perl application that provides comprehensive metadata extraction for hundreds of file formats. It's invoked via shell commands.

**Installation**:
```bash
# macOS
brew install exiftool

# Ubuntu/Debian
sudo apt install libimage-exiftool-perl

# Verify
exiftool -ver
```

**Alternative**: If ExifTool is not available, the system should gracefully degrade or use a PHP-based alternative (implementation TBD).

### Storage

#### Development (Default)
- **Local filesystem** at `storage/app/`
- Originals: `storage/app/assets/originals/`
- Derivatives: `storage/app/assets/derivatives/`

#### Production (Recommended)
- **Amazon S3** or compatible (MinIO, DigitalOcean Spaces)
- **Cloudflare R2** for cost-effective storage
- **Local NAS** for on-premise deployments

Configure via `.env`:
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

### Queue Backend

#### Development (Default)
- **Sync** - Processes jobs immediately

#### Production (Recommended)
- **Redis** - Fast, reliable queue
- **Database** - SQL-based queue
- **Amazon SQS** - Managed queue service

Configure via `.env`:
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Cache Backend

#### Development (Default)
- **Array** or **File** cache

#### Production (Recommended)
- **Redis** - Fast, in-memory cache
- **Memcached** - Alternative in-memory cache

Configure via `.env`:
```env
CACHE_STORE=redis
```

### Database

#### Development (Default)
- **SQLite** - Single file database

#### Production (Recommended)
- **MySQL 8.0+** - Most tested
- **PostgreSQL 13+** - Also supported
- **MariaDB 10.3+** - MySQL alternative

Configure via `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=prophoto
DB_USERNAME=root
DB_PASSWORD=secret
```

## Shared Conventions

### Storage Paths

All packages follow these conventions:

| Type | Path | Description |
|------|------|-------------|
| Originals | `storage/app/assets/originals/{asset_id}/` | Original uploaded files |
| Derivatives | `storage/app/assets/derivatives/{asset_id}/{type}/` | Generated thumbnails, previews, etc. |
| Temp | `storage/app/temp/` | Temporary processing files |

Derivative types: `thumbnail`, `preview`, `web`, `print`

### Queue Naming

| Queue | Purpose | Priority |
|-------|---------|----------|
| `ingest` | Photo ingestion jobs | High |
| `default` | General jobs | Normal |
| `low` | Background cleanup | Low |

### Event Names

Defined in `prophoto-contracts`:

- `ProPhoto\Contracts\Events\AssetIngested`
- `ProPhoto\Contracts\Events\GalleryCreated`
- `ProPhoto\Contracts\Events\PermissionChanged`

### Cache Keys

Prefix all cache keys by package:

- `ingest:metadata:{asset_id}`
- `galleries:gallery:{gallery_id}`
- `access:permissions:{user_id}`

### Asset Tags (Published Files)

When publishing frontend assets:

- `ingest-assets` → `public/vendor/prophoto-ingest/`
- `galleries-assets` → `public/vendor/prophoto-galleries/`
- `access-assets` → `public/vendor/prophoto-access/`
- `debug-assets` → `public/vendor/prophoto-debug/`

## Development Dependencies

### Testing

| Package | Purpose | Where |
|---------|---------|-------|
| pestphp/pest | Test framework | All packages |
| orchestra/testbench | Laravel testing environment | Package tests |
| mockery/mockery | Mocking | Package tests |

### Code Quality

| Package | Purpose | Where |
|---------|---------|-------|
| laravel/pint | Code style fixer | All packages |
| phpstan/phpstan | Static analysis | prophoto-ingest |

### Debugging

| Package | Purpose | Where |
|---------|---------|-------|
| barryvdh/laravel-debugbar | Debug toolbar | Sandbox (dev) |
| laravel/pail | Log viewer | Sandbox (dev) |

## Updating Dependencies

### Update All Composer Dependencies

**In sandbox**:
```bash
cd sandbox
composer update
```

**In package**:
```bash
cd prophoto-{package}
composer update
```

**Workspace-wide via CLI**:
```bash
./scripts/prophoto rebuild
```

### Update Node Dependencies

**In sandbox**:
```bash
cd sandbox
npm update
```

**In package**:
```bash
cd prophoto-{package}
npm update
```

### Security Updates

Check for security vulnerabilities:
```bash
composer audit
npm audit
```

Fix automatically:
```bash
composer update --with-dependencies
npm audit fix
```

## Dependency Conflicts

### Resolving Version Conflicts

If packages require different versions of a dependency:

1. **Update all packages** to use compatible versions
2. **Use version ranges** (`^11.0|^12.0`) instead of exact versions
3. **Extract to contracts** if it's a shared interface

### Laravel Version Strategy

All packages support **two major Laravel versions**:
```json
{
  "require": {
    "illuminate/support": "^11.0|^12.0"
  }
}
```

This allows gradual upgrades without breaking the workspace.

## Minimum Versions

These are the minimum versions tested and supported:

| Dependency | Minimum Version | Recommended |
|------------|-----------------|-------------|
| PHP | 8.2.0 | 8.3+ |
| Composer | 2.0.0 | 2.6+ |
| Node.js | 18.0.0 | 20+ LTS |
| ExifTool | 12.0 | Latest |
| Laravel | 11.0 | 12.x |
| MySQL | 8.0 | 8.0+ |
| PostgreSQL | 13.0 | 15+ |
| Redis | 6.0 | 7+ |

## Platform-Specific Notes

### macOS

- Use Homebrew for all system dependencies
- Laravel Valet recommended for local development
- ExifTool installs cleanly via Homebrew

### Ubuntu/Debian

- Use apt for system dependencies
- May need to add PHP PPA for latest versions:
  ```bash
  sudo add-apt-repository ppa:ondrej/php
  sudo apt update
  ```

### Windows

- Use WSL2 (Windows Subsystem for Linux) recommended
- Or Docker Desktop with Laravel Sail
- Native Windows development possible but not primary focus

### Docker

Use Laravel Sail for containerized development:
```bash
cd sandbox
./vendor/bin/sail up
```

All dependencies packaged in containers.

## Troubleshooting Dependencies

### "ExifTool not found"

```bash
# Install ExifTool
brew install exiftool  # macOS
apt install libimage-exiftool-perl  # Ubuntu

# Verify
exiftool -ver
```

### "Intervention Image errors"

Requires GD or Imagick extension:
```bash
# Install GD
apt install php8.2-gd
# or Imagick
apt install php8.2-imagick
```

### "Class not found" errors

```bash
# Regenerate autoloader
cd sandbox
composer dump-autoload -o

# Or full rebuild
./scripts/prophoto rebuild
```

### Package version conflicts

```bash
# See what's causing conflict
composer why-not prophoto/ingest dev-main

# Update with dependencies
composer update prophoto/ingest --with-dependencies
```

## CI/CD Dependencies

For automated testing/deployment:

- **GitHub Actions** / **GitLab CI** / **Jenkins**
- PHP Docker image: `php:8.2-cli`
- Node Docker image: `node:20`
- Database image: `mysql:8` or `postgres:15`
- Redis image: `redis:7`

See `.github/workflows/` for example CI configuration (TBD).
