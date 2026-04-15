<?php

namespace ProPhoto\AI\Filament\RelationManagers;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use ProPhoto\AI\Models\AiGeneration;
use ProPhoto\AI\Models\AiGeneratedPortrait;
use ProPhoto\AI\Models\AiGenerationRequest;
use ProPhoto\AI\Registry\AiProviderRegistry;
use ProPhoto\AI\Services\AiCostService;
use ProPhoto\AI\Services\AiOrchestrationService;
use ProPhoto\Gallery\Models\Gallery;

/**
 * Story 8.4 — AI Generation relation manager.
 *
 * Displayed on the EditGallery page. Provides the photographer's interface for:
 *  - Enabling AI on a gallery and starting model training
 *  - Monitoring training status with live polling
 *  - Generating portraits from a trained model
 *  - Viewing generated portraits with ImageKit post-processing toggles
 *  - Tracking costs and remaining generation quota
 *
 * The relation is Gallery → AiGeneration (HasOne), but this relation manager
 * shows AiGenerationRequests as table rows (the generation history).
 *
 * Uses Filament v4 namespace conventions throughout.
 */
class AiGenerationRelationManager extends RelationManager
{
    protected static string $relationship = 'aiGeneration';

    protected static ?string $title = 'AI Generation';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-sparkles';

    /**
     * Poll every 5 seconds when training or generation is in progress.
     */
    public function getPolling(): ?string
    {
        $gallery = $this->getOwnerRecord();
        $generation = $gallery->aiGeneration;

        if (! $generation) {
            return null;
        }

        // Poll during active training
        if (in_array($generation->model_status, [AiGeneration::STATUS_PENDING, AiGeneration::STATUS_TRAINING])) {
            return '5s';
        }

        // Poll if any generation requests are processing
        if ($generation->requests()->whereIn('status', [
            AiGenerationRequest::STATUS_PENDING,
            AiGenerationRequest::STATUS_PROCESSING,
        ])->exists()) {
            return '5s';
        }

        return null;
    }

    /**
     * Table showing generation request history.
     *
     * This is a table of AiGenerationRequest rows, not AiGeneration rows.
     * We override the relationship query to show requests instead.
     */
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                // The relationship is Gallery → AiGeneration (HasOne).
                // We want to show AiGenerationRequest rows instead.
                // Override to query requests through the generation.
                $gallery = $this->getOwnerRecord();
                $generation = $gallery->aiGeneration;

                if (! $generation) {
                    return AiGenerationRequest::query()->whereRaw('1 = 0');
                }

                return AiGenerationRequest::query()
                    ->where('ai_generation_id', $generation->id)
                    ->with('portraits');
            })
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->label('#')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'    => 'warning',
                        'processing' => 'primary',
                        'completed'  => 'success',
                        'failed'     => 'danger',
                        default      => 'gray',
                    }),

                Tables\Columns\TextColumn::make('generated_portrait_count')
                    ->label('Portraits')
                    ->alignCenter()
                    ->icon('heroicon-o-photo'),

                Tables\Columns\TextColumn::make('custom_prompt')
                    ->label('Prompt')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('Default prompt'),

                Tables\Columns\TextColumn::make('generation_cost')
                    ->label('Cost')
                    ->money('usd')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                $this->makeViewPortraitsAction(),
            ])
            ->headerActions([
                $this->makeStartTrainingAction(),
                $this->makeGenerateAction(),
            ])
            ->emptyStateHeading($this->getEmptyStateHeading())
            ->emptyStateDescription($this->getEmptyStateDescription())
            ->emptyStateIcon('heroicon-o-sparkles')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }

    // ── Header Actions ──────────────────────────────────────────────────

    /**
     * Start Training action — visible when gallery has AI enabled but no trained model.
     */
    protected function makeStartTrainingAction(): Action
    {
        return Action::make('start_training')
            ->label('Start Training')
            ->icon('heroicon-o-cpu-chip')
            ->color('primary')
            ->visible(function (): bool {
                $gallery = $this->getOwnerRecord();

                if (! $gallery->ai_enabled) {
                    return false;
                }

                $generation = $gallery->aiGeneration;

                // Show if no generation exists, or if previous one failed/expired
                if (! $generation) {
                    return true;
                }

                return in_array($generation->model_status, [
                    AiGeneration::STATUS_FAILED,
                    AiGeneration::STATUS_EXPIRED,
                ]);
            })
            ->requiresConfirmation()
            ->modalHeading('Start AI Model Training')
            ->modalDescription(function (): string {
                $gallery = $this->getOwnerRecord();
                $imageCount = $gallery->images()->count();

                try {
                    $costService = app(AiCostService::class);
                    $registry = app(AiProviderRegistry::class);
                    $providerKey = $registry->default()->providerKey();
                    $cost = $costService->estimateTrainingCost($providerKey, $imageCount);
                    $costStr = '$' . number_format($cost->toDollars(), 2);
                } catch (\Throwable) {
                    $costStr = '(unable to estimate)';
                }

                return "This will train an AI model on {$imageCount} images from this gallery. "
                    . "Training typically takes 15-60 minutes.\n\n"
                    . "Estimated cost: {$costStr}";
            })
            ->action(function (): void {
                $gallery = $this->getOwnerRecord();

                // Collect image URLs from gallery images with assets
                $imageUrls = $gallery->imagesWithAssets()
                    ->get()
                    ->map(fn ($image) => $image->resolvedThumbnailUrl())
                    ->filter()
                    ->values();

                if ($imageUrls->isEmpty()) {
                    Notification::make()
                        ->title('No images available')
                        ->body('Add images to the gallery before starting AI training.')
                        ->danger()
                        ->send();
                    return;
                }

                try {
                    $orchestration = app(AiOrchestrationService::class);
                    $orchestration->initiateTraining(
                        gallery: $gallery,
                        imageUrls: $imageUrls,
                        userId: auth()->id(),
                    );

                    Notification::make()
                        ->title('AI training started')
                        ->body('Model training has been queued. This page will update automatically.')
                        ->success()
                        ->send();
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Training failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Generate Portraits action — visible when model is trained and quota remains.
     */
    protected function makeGenerateAction(): Action
    {
        return Action::make('generate_portraits')
            ->label('Generate Portraits')
            ->icon('heroicon-o-paint-brush')
            ->color('success')
            ->visible(function (): bool {
                $gallery = $this->getOwnerRecord();
                $generation = $gallery->aiGeneration;

                if (! $generation || ! $generation->isReady()) {
                    return false;
                }

                return $generation->remaining_generations > 0;
            })
            ->modalHeading('Generate AI Portraits')
            ->modalDescription(function (): string {
                $gallery = $this->getOwnerRecord();
                $generation = $gallery->aiGeneration;
                $remaining = $generation->remaining_generations;

                try {
                    $costService = app(AiCostService::class);
                    $cost = $costService->estimateGenerationCost($generation->provider_key, 8);
                    $costStr = '$' . number_format($cost->toDollars(), 2);
                } catch (\Throwable) {
                    $costStr = '(unable to estimate)';
                }

                return "{$remaining} of 5 generations remaining. Cost: {$costStr} per generation (up to 8 portraits).";
            })
            ->form([
                Forms\Components\Textarea::make('prompt')
                    ->label('Custom Prompt (optional)')
                    ->helperText('Leave blank to use the default portrait prompt.')
                    ->rows(3),
            ])
            ->action(function (array $data): void {
                $gallery = $this->getOwnerRecord();
                $generation = $gallery->aiGeneration;

                try {
                    $orchestration = app(AiOrchestrationService::class);
                    $orchestration->initiateGeneration(
                        generation: $generation,
                        prompt: $data['prompt'] ?: null,
                        numImages: 8,
                        userId: auth()->id(),
                    );

                    Notification::make()
                        ->title('Portrait generation started')
                        ->body('Generation typically takes 30-90 seconds. This page will update automatically.')
                        ->success()
                        ->send();
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Generation failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    // ── Row Actions ─────────────────────────────────────────────────────

    /**
     * View Portraits action — opens a modal with thumbnail grid and post-processing toggles.
     */
    protected function makeViewPortraitsAction(): Action
    {
        return Action::make('view_portraits')
            ->label('View Portraits')
            ->icon('heroicon-o-eye')
            ->color('primary')
            ->visible(fn (AiGenerationRequest $record): bool =>
                $record->status === AiGenerationRequest::STATUS_COMPLETED
                && $record->generated_portrait_count > 0
            )
            ->modalHeading(fn (AiGenerationRequest $record): string =>
                "Portraits — Request #{$record->request_number}"
            )
            ->modalContent(fn (AiGenerationRequest $record): HtmlString =>
                new HtmlString($this->renderPortraitGrid($record))
            )
            ->modalWidth('7xl')
            ->modalSubmitAction(false);
    }

    // ── Portrait Rendering ──────────────────────────────────────────────

    /**
     * Render the portrait thumbnail grid with post-processing toggle hints.
     *
     * Post-processing transforms are applied by appending ImageKit URL params:
     *   - Background removal: tr:e-bgremove
     *   - Enhance/retouch: tr:e-retouch
     *   - Upscale: tr:e-upscale
     *
     * These are display-time URL transforms — no server calls, no stored changes.
     * ImageKit bills by extension unit usage automatically.
     */
    protected function renderPortraitGrid(AiGenerationRequest $request): string
    {
        $portraits = $request->portraits()->orderBy('sort_order')->get();

        if ($portraits->isEmpty()) {
            return '<p class="text-gray-500 text-center py-8">No portraits generated.</p>';
        }

        $html = '<div class="space-y-4">';

        // Prompt info
        $prompt = $request->custom_prompt ?? 'Default portrait prompt';
        $cost = '$' . number_format($request->generation_cost, 2);
        $date = $request->created_at->format('M j, Y g:i A');
        $html .= "<div class=\"text-sm text-gray-500 px-2\">"
            . "<strong>Prompt:</strong> {$this->escapeHtml($prompt)}<br>"
            . "<strong>Cost:</strong> {$cost} &middot; <strong>Date:</strong> {$date}"
            . "</div>";

        // Post-processing hint
        $html .= '<div class="text-xs text-gray-400 px-2 py-2 bg-gray-50 rounded">'
            . 'Post-processing transforms available via ImageKit URL params: '
            . '<code>tr:e-bgremove</code> (background removal), '
            . '<code>tr:e-retouch</code> (enhance), '
            . '<code>tr:e-upscale</code> (upscale). '
            . 'These are applied on-the-fly via your ImageKit plan.'
            . '</div>';

        // Thumbnail grid
        $html .= '<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 p-2">';

        foreach ($portraits as $portrait) {
            $thumbnailUrl = $this->escapeHtml($portrait->imagekit_thumbnail_url ?? $portrait->imagekit_url);
            $fullUrl = $this->escapeHtml($portrait->imagekit_url);
            $downloaded = $portrait->downloaded_by_subject ? '✓ Downloaded' : '';

            $html .= '<div class="relative group">'
                . "<a href=\"{$fullUrl}\" target=\"_blank\" class=\"block\">"
                . "<img src=\"{$thumbnailUrl}\" "
                . "class=\"w-full aspect-square object-cover rounded-lg shadow-sm hover:shadow-md transition-shadow\" "
                . "alt=\"AI Portrait\" loading=\"lazy\" />"
                . '</a>'
                . "<div class=\"mt-1 text-xs text-gray-400 text-center\">"
                . ($portrait->file_size ? number_format($portrait->file_size / 1024) . ' KB' : '')
                . ($downloaded ? " &middot; <span class=\"text-green-600\">{$downloaded}</span>" : '')
                . '</div>'
                . '</div>';
        }

        $html .= '</div></div>';

        return $html;
    }

    // ── Empty State ─────────────────────────────────────────────────────

    protected function getEmptyStateHeading(): string
    {
        $gallery = $this->getOwnerRecord();
        $generation = $gallery->aiGeneration;

        if (! $gallery->ai_enabled) {
            return 'AI Generation is not enabled';
        }

        if (! $generation) {
            return 'No AI model trained yet';
        }

        if ($generation->model_status === AiGeneration::STATUS_PENDING) {
            return 'Training queued...';
        }

        if ($generation->model_status === AiGeneration::STATUS_TRAINING) {
            return 'Model is training...';
        }

        if ($generation->model_status === AiGeneration::STATUS_TRAINED) {
            return 'No generations yet';
        }

        if ($generation->model_status === AiGeneration::STATUS_FAILED) {
            return 'Training failed';
        }

        return 'Ready to generate';
    }

    protected function getEmptyStateDescription(): string
    {
        $gallery = $this->getOwnerRecord();
        $generation = $gallery->aiGeneration;

        if (! $gallery->ai_enabled) {
            return 'Enable AI on this gallery to start generating portraits.';
        }

        if (! $generation) {
            return 'Click "Start Training" to train an AI model on this gallery\'s images.';
        }

        if (in_array($generation->model_status, [AiGeneration::STATUS_PENDING, AiGeneration::STATUS_TRAINING])) {
            return 'Training typically takes 15-60 minutes. This page will update automatically.';
        }

        if ($generation->model_status === AiGeneration::STATUS_TRAINED) {
            return 'Click "Generate Portraits" to create AI-generated portraits.';
        }

        if ($generation->model_status === AiGeneration::STATUS_FAILED) {
            $error = $generation->error_message ?? 'Unknown error';
            return "Error: {$error}. You can retry training.";
        }

        return '';
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    protected function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
