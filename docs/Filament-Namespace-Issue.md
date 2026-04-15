# Filament v3 → v4 Namespace Migration Guide

> **Purpose**: Complete reference for all Filament namespace changes that affect ProPhoto. This document was built from real errors encountered during the v4 migration — every entry below caused a runtime crash until fixed. **Read this before writing any Filament code.**

---

## The Big Picture

Filament v4 reorganized its class hierarchy. The three major changes are:

1. **Actions moved** — from `Filament\Tables\Actions\*` to `Filament\Actions\*`
2. **Layout components moved** — from `Filament\Forms\Components\*` to `Filament\Schemas\Components\*`
3. **Property types widened** — several `?string` properties now accept `UnitEnum|string|null` or `BackedEnum|string|null`

Input components (TextInput, Select, Toggle, etc.) and table components (columns, filters) stayed where they were.

---

## Complete Namespace Migration Map

### Actions (all moved to `Filament\Actions\*`)

| v3 Namespace | v4 Namespace |
|-------------|-------------|
| `Filament\Tables\Actions\Action` | `Filament\Actions\Action` |
| `Filament\Tables\Actions\EditAction` | `Filament\Actions\EditAction` |
| `Filament\Tables\Actions\DeleteAction` | `Filament\Actions\DeleteAction` |
| `Filament\Tables\Actions\ViewAction` | `Filament\Actions\ViewAction` |
| `Filament\Tables\Actions\CreateAction` | `Filament\Actions\CreateAction` |
| `Filament\Tables\Actions\ActionGroup` | `Filament\Actions\ActionGroup` |
| `Filament\Tables\Actions\BulkAction` | `Filament\Actions\BulkAction` |
| `Filament\Tables\Actions\BulkActionGroup` | `Filament\Actions\BulkActionGroup` |
| `Filament\Tables\Actions\DeleteBulkAction` | `Filament\Actions\DeleteBulkAction` |

**Note:** `Filament\Notifications\Actions\Action` (used inside Notification builders) is unchanged.

### Layout / Structural Components (moved to `Filament\Schemas\Components\*`)

| v3 Namespace | v4 Namespace |
|-------------|-------------|
| `Filament\Forms\Components\Section` | `Filament\Schemas\Components\Section` |
| `Filament\Forms\Components\Wizard` | `Filament\Schemas\Components\Wizard` |
| `Filament\Forms\Components\Wizard\Step` | `Filament\Schemas\Components\Wizard\Step` |
| `Filament\Forms\Components\Grid` | `Filament\Schemas\Components\Grid` |
| `Filament\Forms\Components\Placeholder` | `Filament\Schemas\Components\Placeholder` |
| `Filament\Forms\Components\Hidden` | `Filament\Schemas\Components\Hidden` |
| `Filament\Forms\Components\Repeater` | `Filament\Schemas\Components\Repeater` |
| `Filament\Forms\Components\Tabs` | `Filament\Schemas\Components\Tabs` |
| `Filament\Forms\Components\Fieldset` | `Filament\Schemas\Components\Fieldset` |
| `Filament\Forms\Components\Split` | `Filament\Schemas\Components\Split` |
| `Filament\Forms\Components\Group` | `Filament\Schemas\Components\Group` |

### Utility Classes (moved to `Filament\Schemas\Components\Utilities\*`)

| v3 Namespace | v4 Namespace |
|-------------|-------------|
| `Filament\Forms\Get` | `Filament\Schemas\Components\Utilities\Get` |
| `Filament\Forms\Set` | `Filament\Schemas\Components\Utilities\Set` |

### Return Type Changes

| v3 Return Type | v4 Return Type |
|---------------|---------------|
| `Filament\Forms\Components\Component` | `Filament\Schemas\Components\Component` |

### Input Components (UNCHANGED — stay in `Filament\Forms\Components\*`)

These did NOT move:
- `TextInput`, `Textarea`, `Select`, `Toggle`, `Checkbox`, `CheckboxList`
- `Radio`, `DateTimePicker`, `DatePicker`, `TimePicker`
- `FileUpload`, `RichEditor`, `MarkdownEditor`, `ColorPicker`
- `TagsInput`, `KeyValue`, `Repeater` (input version)

### Table Components (UNCHANGED — stay in `Filament\Tables\*`)

These did NOT move:
- `Filament\Tables\Table`
- `Filament\Tables\Columns\TextColumn`, `IconColumn`, `ImageColumn`, `ToggleColumn`
- `Filament\Tables\Filters\SelectFilter`, `TernaryFilter`, `Filter`

### Enums (removed or changed)

| v3 | v4 | Notes |
|----|-----|-------|
| `Filament\Tables\Columns\IconColumn\IconColumnSize::Medium` | `'md'` (string) | Enum class removed; use string sizes: `'sm'`, `'md'`, `'lg'` |
| `Filament\Tables\Columns\BadgeColumn` | Removed entirely | Use `TextColumn::make()->badge()` instead |

---

## Property Type Changes on Resource / RelationManager

### `$navigationGroup`
```php
// v3
protected static ?string $navigationGroup = 'Galleries';

// v4
protected static \UnitEnum|string|null $navigationGroup = 'Galleries';
```

### `$navigationIcon`
```php
// v3
protected static ?string $navigationIcon = 'heroicon-o-photo';

// v4
protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';
```

### `$icon` (on RelationManagers)
```php
// v3
protected static ?string $icon = 'heroicon-o-photo';

// v4
protected static string|\BackedEnum|null $icon = 'heroicon-o-photo';
```

### Properties that did NOT change (still `?string`):
- `$navigationLabel`
- `$modelLabel`
- `$pluralModelLabel`
- `$model`

---

## Method Signature Changes

### `form()` on Resources
```php
// v3
public static function form(Form $form): Form

// v4
public static function form(Form|\Filament\Schemas\Schema $form): \Filament\Schemas\Schema
```

### `getTableQuery()` on RelationManagers
```php
// v3 — override getTableQuery()
protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
{
    return parent::getTableQuery()
        ->with(['asset.derivatives'])
        ->orderBy('sort_order');
}

// v4 — use modifyQueryUsing() in table() instead
public function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) =>
            $query->with(['asset.derivatives'])->orderBy('sort_order')
        )
        ->columns([...]);
}
```

---

## Sandbox-Specific Notes

- The `create-sandbox.sh` script installs `filament/filament:"^4.0"`
- After installing Filament, run `php artisan filament:assets` to publish CSS/JS
- Filament's `databaseNotifications()` requires Laravel's `notifications` table — the sandbox script creates it via `php artisan make:notifications-table`
- Standard v4 color tokens: `primary`, `success`, `warning`, `danger`, `gray` (no `info` — use `primary` instead)

---

## Quick Checklist for New Filament Code

When writing any new Filament resource, relation manager, or action:

- [ ] Actions: import from `Filament\Actions\*`, not `Filament\Tables\Actions\*`
- [ ] Layout components (Section, Grid, Wizard, Placeholder): import from `Filament\Schemas\Components\*`
- [ ] Input components (TextInput, Select, Toggle): import from `Filament\Forms\Components\*`
- [ ] `Get`/`Set` closures: import from `Filament\Schemas\Components\Utilities\*`
- [ ] `$navigationGroup`: type as `\UnitEnum|string|null`
- [ ] `$navigationIcon` / `$icon`: type as `string|\BackedEnum|null`
- [ ] Component sizes: use string `'sm'`, `'md'`, `'lg'` — not enum classes
- [ ] No `BadgeColumn` — use `TextColumn::make()->badge()`
- [ ] Query customization on RelationManagers: use `->modifyQueryUsing()`, not `getTableQuery()`
- [ ] Return types for helper methods returning layout components: use `\Filament\Schemas\Components\Component`

---

## Files Fixed in ProPhoto (Sprint 5 v4 Migration)

| File | What Changed |
|------|-------------|
| `GalleryResource.php` | BadgeColumn import removed, Section/Grid/Wizard/Placeholder/Hidden/Repeater/Step moved to Schemas, Get/Set moved to Schemas Utilities, form() signature updated, $navigationIcon/$navigationGroup types widened, action imports moved, return type on buildPendingTypesChecklist() updated, GalleryActivityRelationManager import added |
| `PendingTypeTemplateResource.php` | Section already in Schemas, $navigationIcon/$navigationGroup types widened, Placeholder moved to Schemas, action imports moved |
| `AccessLogResource.php` | $navigationIcon/$navigationGroup types widened |
| `GalleryImagesRelationManager.php` | BadgeColumn→TextColumn->badge(), $icon type widened, getTableQuery()→modifyQueryUsing(), action imports moved, Placeholder moved to Schemas |
| `GalleryActivityRelationManager.php` | $icon type widened, IconColumnSize::Medium→'md' |
| `GenerateShareLinkAction.php` | Action import moved from Tables to Actions |
| `AddImagesFromSessionAction.php` | Action import moved from Tables to Actions |
| `GalleryPlugin.php` | No changes needed (Plugin contract unchanged) |
| `RecentSubmissionsWidget.php` | No changes needed (TableWidget unchanged) |

---

*Last updated: 2026-04-14 — Complete v4 migration reference built from ProPhoto sandbox testing*
