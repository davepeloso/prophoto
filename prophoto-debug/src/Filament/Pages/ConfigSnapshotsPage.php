<?php

namespace ProPhoto\Debug\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use ProPhoto\Debug\Models\ConfigSnapshot;
use ProPhoto\Debug\Services\ConfigRecorder;

class ConfigSnapshotsPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'debug::filament.pages.config-snapshots';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-camera';
    }

    public static function getNavigationLabel(): string
    {
        return 'Config Snapshots';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Debug';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Configuration Snapshots';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ConfigSnapshot::query()->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->placeholder('-'),
                TextColumn::make('thumbnail_quality')
                    ->label('Thumb Quality')
                    ->placeholder('-'),
                TextColumn::make('preview_max_dimension')
                    ->label('Preview Max')
                    ->suffix('px')
                    ->placeholder('-'),
                TextColumn::make('exiftool_speed_mode')
                    ->label('Speed Mode')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'fast2' => 'success',
                        'fast' => 'info',
                        'full' => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('-'),
                TextColumn::make('queue_connection')
                    ->label('Queue')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (ConfigSnapshot $record) => "Snapshot: {$record->name}")
                    ->modalContent(fn (ConfigSnapshot $record) => view('debug::filament.components.snapshot-detail', [
                        'snapshot' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                DeleteAction::make(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Create Snapshot')
                    ->icon('heroicon-o-plus')
                    ->form([
                        TextInput::make('name')
                            ->label('Snapshot Name')
                            ->required()
                            ->placeholder('e.g., Testing RAW thumbnail extraction'),
                        Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Describe what configuration you are testing...')
                            ->rows(3),
                    ])
                    ->action(function (array $data, ConfigRecorder $recorder): void {
                        $recorder->snapshot($data['name'], $data['description'] ?? null);

                        Notification::make()
                            ->title('Snapshot created successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->resetTable()),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return config('debug.enabled', false) && config('debug.filament.enabled', true);
    }
}
