# ProPhoto Package Creation Guide

This guide documents lessons learned and patterns for creating Laravel packages with Filament integration.

## Prompt for Claude: Creating a New Package with Uninstall Script

```
Create a new Laravel package called prophoto-[NAME] with:

1. **Package Structure:**
   - composer.json with prophoto/[NAME] namespace
   - Service provider at src/[Name]ServiceProvider.php
   - Config file at config/[name].php
   - Migrations at database/migrations/
   - Models at src/Models/
   - Services at src/Services/

2. **Filament Pages** (if needed):
   Follow these CRITICAL patterns for Filament v4 compatibility:

   ```php
   use Filament\Pages\Page;
   use Illuminate\Contracts\Support\Htmlable;

   class MyPage extends Page implements HasForms, HasTable
   {
       // $view is NON-STATIC (no static keyword!)
       protected string $view = '[name]::filament.pages.my-page';

       // Navigation methods ARE static
       public static function getNavigationIcon(): ?string
       {
           return 'heroicon-o-[icon]';
       }

       public static function getNavigationLabel(): string
       {
           return 'My Page';
       }

       public static function getNavigationGroup(): ?string
       {
           return '[Group Name]';
       }

       public static function getNavigationSort(): ?int
       {
           return 1;
       }

       // getTitle() is NON-STATIC with union return type!
       public function getTitle(): string|Htmlable
       {
           return 'My Page Title';
       }

       public static function shouldRegisterNavigation(): bool
       {
           return config('[name].enabled', false);
       }
   }
   ```

3. **Uninstall Script** at scripts/uninstall.sh:
   - Copy from /Users/davepeloso/Herd-Profoto/scripts/package-uninstall-template.sh
   - Customize: PACKAGE_NAME, PACKAGE_SLUG, CONFIG_FILE, VIEW_VENDOR_DIR, DATABASE_TABLES

4. **Artisan Uninstall Command** at src/Console/Commands/UninstallCommand.php:
   - Options: --drop-tables, --remove-config, --remove-views, --all, --force
   - Register in ServiceProvider

5. **Documentation:**
   - README.md with installation steps
   - scripts/usage.md with uninstall examples
```

## Filament Compatibility Checklist

| Property/Method | Static? | Return Type | Notes |
|-----------------|---------|-------------|-------|
| `$view` | NO | `string` | Instance property |
| `getNavigationIcon()` | YES | `?string` | Can return null |
| `getNavigationLabel()` | YES | `string` | Required |
| `getNavigationGroup()` | YES | `?string` | Can return null |
| `getNavigationSort()` | YES | `?int` | Can return null |
| `getTitle()` | NO | `string\|Htmlable` | Union type required! |
| `shouldRegisterNavigation()` | YES | `bool` | Controls visibility |

## Filament v4 Action Namespaces

In Filament v4, all actions moved to `Filament\Actions\*`:

| Old (v3) | New (v4) |
|----------|----------|
| `Filament\Tables\Actions\Action` | `Filament\Actions\Action` |
| `Filament\Tables\Actions\DeleteAction` | `Filament\Actions\DeleteAction` |
| `Filament\Tables\Actions\ViewAction` | `Filament\Actions\ViewAction` |
| `Filament\Tables\Actions\EditAction` | `Filament\Actions\EditAction` |

Table method changes:
| Old (v3) | New (v4) |
|----------|----------|
| `->actions([...])` | `->recordActions([...])` |
| `->bulkActions([...])` | `->toolbarActions([...])` |

## Common Errors and Fixes

### Error: "Cannot redeclare non static...::$view as static"
**Fix:** Remove `static` keyword from `$view` property
```php
// WRONG
protected static string $view = '...';

// CORRECT
protected string $view = '...';
```

### Error: "Cannot make non static method...::getTitle() static"
**Fix:** Remove `static` keyword from `getTitle()` method
```php
// WRONG
public static function getTitle(): string

// CORRECT
public function getTitle(): string|Htmlable
```

### Error: "Type of...::$navigationGroup must be UnitEnum|string|null"
**Fix:** Use method instead of property for navigation settings
```php
// WRONG
protected static ?string $navigationGroup = 'Debug';

// CORRECT
public static function getNavigationGroup(): ?string
{
    return 'Debug';
}
```

## Uninstall Script Usage

```bash
# Basic uninstall
./scripts/uninstall.sh /path/to/app

# With table dropping
./scripts/uninstall.sh /path/to/app --drop-tables

# Skip confirmations
./scripts/uninstall.sh /path/to/app --drop-tables --force

# Show help
./scripts/uninstall.sh --help
```

## Package Dependencies

When the package depends on Filament, ensure composer.json includes:
```json
{
    "require": {
        "php": "^8.2",
        "filament/filament": "^3.0|^4.0",
        "illuminate/support": "^11.0|^12.0"
    }
}
```

## Testing After Installation

```bash
cd /path/to/app

# Clear all caches first
php artisan optimize:clear
composer dump-autoload

# Run migrations
php artisan migrate

# Publish config
php artisan vendor:publish --tag=[name]-config

# Verify Filament pages load
php artisan route:list | grep filament
```
