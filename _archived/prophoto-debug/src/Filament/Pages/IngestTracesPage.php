<?php

namespace ProPhoto\Debug\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use ProPhoto\Debug\Models\IngestTrace;
use ProPhoto\Debug\Services\QueueStatusService;

class IngestTracesPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'debug::filament.pages.ingest-traces';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bug-ant';
    }

    public static function getNavigationLabel(): string
    {
        return 'Ingest Traces';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Debug';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Ingest Decision Traces';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public ?string $filterUuid = null;

    public ?string $filterDateFrom = null;

    public ?string $filterDateTo = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(IngestTrace::query()->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable()
                    ->copyable()
                    ->limit(8)
                    ->tooltip(fn ($record) => $record->uuid),
                TextColumn::make('trace_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'preview_extraction' => 'info',
                        'metadata_extraction' => 'warning',
                        'thumbnail_generation' => 'success',
                        'enhancement' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('method_tried')
                    ->label('Method')
                    ->searchable(),
                TextColumn::make('method_order')
                    ->label('Order')
                    ->alignCenter(),
                IconColumn::make('success')
                    ->label('Success')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('failure_reason')
                    ->label('Failure Reason')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->failure_reason)
                    ->placeholder('-'),
                TextColumn::make('result_info.duration_ms')
                    ->label('Duration')
                    ->suffix('ms')
                    ->placeholder('-'),
                TextColumn::make('result_info.size')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' B' : '-')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('M d, H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('trace_type')
                    ->label('Type')
                    ->options([
                        'preview_extraction' => 'Preview Extraction',
                        'metadata_extraction' => 'Metadata Extraction',
                        'thumbnail_generation' => 'Thumbnail Generation',
                        'enhancement' => 'Enhancement',
                    ]),
                SelectFilter::make('success')
                    ->label('Status')
                    ->options([
                        '1' => 'Success',
                        '0' => 'Failed',
                    ]),
                Filter::make('uuid')
                    ->form([
                        TextInput::make('uuid')
                            ->label('Filter by UUID')
                            ->placeholder('Enter UUID...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['uuid'],
                            fn (Builder $query, $uuid): Builder => $query->where('uuid', 'like', "%{$uuid}%")
                        );
                    }),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('to')
                            ->label('To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date)
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->poll('10s');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearAll')
                ->label('Clear All Traces')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Clear All Traces')
                ->modalDescription('Are you sure you want to delete all trace records? This cannot be undone.')
                ->modalSubmitActionLabel('Yes, clear all')
                ->action(function () {
                    $count = IngestTrace::count();
                    IngestTrace::truncate();

                    Notification::make()
                        ->title("Cleared {$count} traces")
                        ->success()
                        ->send();
                }),
            Action::make('clearOld')
                ->label('Clear Old (7+ days)')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Delete traces older than 7 days?')
                ->action(function () {
                    $count = IngestTrace::where('created_at', '<', now()->subDays(7))->count();
                    IngestTrace::where('created_at', '<', now()->subDays(7))->delete();

                    Notification::make()
                        ->title("Cleared {$count} old traces")
                        ->success()
                        ->send();
                }),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return config('debug.enabled', false) && config('debug.filament.enabled', true);
    }

    /**
     * Get queue status for the view
     */
    public function getQueueStatus(): array
    {
        return app(QueueStatusService::class)->getStatus();
    }
}
