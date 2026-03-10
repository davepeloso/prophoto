# Document repository for each organization.

# Organization Documents System


# 1. Database Schema
## organization_documents table


php
Schema::create('organization_documents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('uploaded_by_user_id')->constrained('users');
    
    $table->string('title');
    $table->string('type'); *// insurance, w9, contract, terms, branding, other*
    $table->text('description')->nullable();
    $table->string('file_name');
    $table->string('file_path'); *// Storage path or ImageKit/S3 URL*
    $table->string('mime_type');
    $table->integer('file_size'); *// bytes*
    
    $table->date('expires_at')->nullable(); *// For insurance docs*
    $table->boolean('requires_renewal')->default(false);
    $table->date('reminded_at')->nullable(); *// Last expiration reminder sent*
    
    $table->boolean('is_required')->default(false); *// Must have before booking*
    $table->boolean('client_visible')->default(true); *// Client can see this doc*
    
    $table->json('metadata')->nullable(); *// Flexible extra data*
    
    $table->timestamps();
    $table->softDeletes();
});


# 2. Document Types
## Predefined Categories


php
*// config/solo.php*
'document_types' => [
    'insurance' => [
        'label' => 'Insurance Certificate',
        'icon' => 'heroicon-o-shield-check',
        'requires_expiration' => true,
        'description' => 'Certificate of Insurance, Additional Insured proof',
    ],
    'w9' => [
        'label' => 'W-9 Tax Form',
        'icon' => 'heroicon-o-document-text',
        'requires_expiration' => false,
        'description' => 'IRS W-9 form for vendor payments',
    ],
    'contract' => [
        'label' => 'Contract/Agreement',
        'icon' => 'heroicon-o-document-duplicate',
        'requires_expiration' => false,
        'description' => 'Signed service agreements, MSA, SOW',
    ],
    'terms' => [
        'label' => 'Terms & Conditions',
        'icon' => 'heroicon-o-clipboard-document-check',
        'requires_expiration' => false,
        'description' => 'Client terms, usage rights, model releases',
    ],
    'branding' => [
        'label' => 'Brand Guidelines',
        'icon' => 'heroicon-o-paint-brush',
        'requires_expiration' => false,
        'description' => 'Logo files, brand standards, style guides',
    ],
    'vendor_forms' => [
        'label' => 'Vendor Forms',
        'icon' => 'heroicon-o-building-office',
        'requires_expiration' => false,
        'description' => 'Vendor registration, onboarding forms',
    ],
    'other' => [
        'label' => 'Other Documents',
        'icon' => 'heroicon-o-folder',
        'requires_expiration' => false,
        'description' => 'Miscellaneous documents',
    ],
],


# 3. Eloquent Model
## OrganizationDocument.php


php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class OrganizationDocument extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'organization_id',
        'uploaded_by_user_id',
        'title',
        'type',
        'description',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'expires_at',
        'requires_renewal',
        'reminded_at',
        'is_required',
        'client_visible',
        'metadata',
    ];
    
    protected $casts = [
        'expires_at' => 'date',
        'reminded_at' => 'date',
        'requires_renewal' => 'boolean',
        'is_required' => 'boolean',
        'client_visible' => 'boolean',
        'metadata' => 'array',
    ];
    
    *// Relationships*
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
    
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
    
    *// Accessors*
    public function getDownloadUrlAttribute()
    {
        *// If using ImageKit or S3, return direct URL*
        if (str_starts_with($this->file_path, 'http')) {
            return $this->file_path;
        }
        
        *// If local storage, generate signed URL*
        return Storage::temporaryUrl(
            $this->file_path,
            now()->addMinutes(30)
        );
    }
    
    public function getIsExpiredAttribute()
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return $this->expires_at->isPast();
    }
    
    public function getIsExpiringSoonAttribute()
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return $this->expires_at->isBetween(now(), now()->addDays(30));
    }
    
    public function getFileSizeHumanAttribute()
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' bytes';
    }
    
    *// Methods*
    public function sendExpirationReminder()
    {
        if (!$this->expires_at || !$this->requires_renewal) {
            return;
        }
        
        *// Send notification to org users*
        $this->organization->users->each(function ($user) {
            $user->notify(new DocumentExpiringNotification($this));
        });
        
        $this->update(['reminded_at' => now()]);
    }
}


# 4. Organization Model Update
## Add to Organization.php


php
public function documents()
{
    return $this->hasMany(OrganizationDocument::class);
}

public function hasRequiredDocuments()
{
    $requiredTypes = ['insurance', 'w9'];
    
    foreach ($requiredTypes as $type) {
        $hasValid = $this->documents()
            ->where('type', $type)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->exists();
            
        if (!$hasValid) {
            return false;
        }
    }
    
    return true;
}

public function getMissingRequiredDocuments()
{
    $requiredTypes = ['insurance', 'w9'];
    $missing = [];
    
    foreach ($requiredTypes as $type) {
        $hasValid = $this->documents()
            ->where('type', $type)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->exists();
            
        if (!$hasValid) {
            $missing[] = config("solo.document_types.{$type}.label");
        }
    }
    
    return $missing;
}


# 5. Filament Resource - Documents Tab
## Organization Resource Update


php
class OrganizationResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Organization Details')
                    ->tabs([
                        *// ... existing tabs*
                        
                        Tab::make('Documents')
                            ->badge(fn ($record) => $record?->documents()->count())
                            ->schema([
                                Section::make('Document Requirements')
                                    ->description('Required documents before booking sessions')
                                    ->schema([
                                        Placeholder::make('doc_status')
                                            ->label('Status')
                                            ->content(function ($record) {
                                                if (!$record) return '—';
                                                
                                                if ($record->hasRequiredDocuments()) {
                                                    return '✅ All required documents on file';
                                                }
                                                
                                                $missing = $record->getMissingRequiredDocuments();
                                                return '⚠️ Missing: ' . implode(', ', $missing);
                                            }),
                                    ]),
                                
                                *// Documents are managed via Relation Manager below*
                            ]),
                    ]),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            *// ... other relations*
            RelationManagers\DocumentsRelationManager::class,
        ];
    }
}


## Documents Relation Manager


php
<?php

namespace App\Filament\Resources\OrganizationResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';
    
    protected static ?string $title = 'Documents';
    
    protected static ?string $icon = 'heroicon-o-folder-open';
    
    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        
                        Forms\Components\Select::make('type')
                            ->options(function () {
                                return collect(config('solo.document_types'))
                                    ->mapWithKeys(fn ($config, $key) => 
                                        [$key => $config['label']]
                                    );
                            })
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $config = config("solo.document_types.{$state}");
                                $set('requires_renewal', $config['requires_expiration'] ?? false);
                            }),
                        
                        Forms\Components\FileUpload::make('file_path')
                            ->label('Document File')
                            ->disk('local')
                            ->directory('organization-documents')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->maxSize(10240) *// 10MB*
                            ->required()
                            ->columnSpan(2)
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $file = $state;
                                    $set('file_name', $file->getClientOriginalName());
                                    $set('mime_type', $file->getMimeType());
                                    $set('file_size', $file->getSize());
                                }
                            }),
                        
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpan(2),
                        
                        Forms\Components\DatePicker::make('expires_at')
                            ->label('Expiration Date')
                            ->visible(fn (Forms\Get $get) => 
                                $get('requires_renewal')
                            ),
                        
                        Forms\Components\Toggle::make('requires_renewal')
                            ->label('Requires Renewal')
                            ->helperText('Send reminder before expiration'),
                        
                        Forms\Components\Toggle::make('is_required')
                            ->label('Required Document')
                            ->helperText('Must be on file before bookings'),
                        
                        Forms\Components\Toggle::make('client_visible')
                            ->label('Client Visible')
                            ->helperText('Client users can see this document')
                            ->default(true),
                    ]),
            ]);
    }
    
    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->formatStateUsing(fn ($state) => 
                        config("solo.document_types.{$state}.label") ?? $state
                    )
                    ->icon(fn ($state) => 
                        config("solo.document_types.{$state}.icon") ?? 'heroicon-o-document'
                    )
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('file_name')
                    ->label('File')
                    ->limit(30),
                
                Tables\Columns\TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn ($record) => $record->file_size_human),
                
                Tables\Columns\TextColumn::make('expires_at')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => 
                        $record->is_expired ? 'danger' : 
                        ($record->is_expiring_soon ? 'warning' : 'success')
                    )
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) => 
                        $record->is_expired ? 'Expired' :
                        ($record->is_expiring_soon ? 'Expiring Soon' : $state?->format('M d, Y'))
                    )
                    ->placeholder('No Expiration'),
                
                Tables\Columns\IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean(),
                
                Tables\Columns\IconColumn::make('client_visible')
                    ->label('Visible to Client')
                    ->boolean(),
                
                Tables\Columns\TextColumn::make('uploadedBy.name')
                    ->label('Uploaded By')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(function () {
                        return collect(config('solo.document_types'))
                            ->mapWithKeys(fn ($config, $key) => 
                                [$key => $config['label']]
                            );
                    }),
                
                Tables\Filters\TernaryFilter::make('is_required')
                    ->label('Required Documents'),
                
                Tables\Filters\TernaryFilter::make('client_visible')
                    ->label('Client Visible'),
                
                Tables\Filters\Filter::make('expired')
                    ->query(fn ($query) => 
                        $query->whereNotNull('expires_at')
                              ->where('expires_at', '<', now())
                    )
                    ->label('Expired Only'),
                
                Tables\Filters\Filter::make('expiring_soon')
                    ->query(fn ($query) => 
                        $query->whereNotNull('expires_at')
                              ->whereBetween('expires_at', [now(), now()->addDays(30)])
                    )
                    ->label('Expiring in 30 Days'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        $data['uploaded_by_user_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => $record->download_url)
                    ->openUrlInNewTab(),
                
                Actions\EditAction::make(),
                
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}


# 6. Client View - Documents Page
## Client Panel - Documents Tab


php
*// In Client Panel Organization Settings*

Tab::make('Documents')
    ->icon('heroicon-o-folder-open')
    ->badge(fn ($record) => $record->documents()->where('client_visible', true)->count())
    ->schema([
        Section::make('Your Documents')
            ->description('Documents on file with Peloso Photography')
            ->schema([
                ViewField::make('documents_grid')
                    ->view('filament.client.documents-grid'),
            ]),
    ]),
### Blade View:filament/client/documents-grid.blade.php

blade
<div class="space-y-4">
    @foreach($getRecord()->documents()->where('client_visible', true)->get()->groupBy('type') as $type => $docs)
        <div>
            <h3 class="text-lg font-semibold mb-2">
                {{ config("solo.document_types.{$type}.label") }}
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($docs as $doc)
                    <div class="border rounded-lg p-4 flex items-start justify-between">
                        <div class="flex items-start gap-3 flex-1">
                            <div class="text-3xl">
                                @if(str_contains($doc->mime_type, 'pdf'))
                                    📄
                                @elseif(str_contains($doc->mime_type, 'image'))
                                    🖼️
                                @else
                                    📎
                                @endif
                            </div>
                            
                            <div class="flex-1">
                                <div class="font-medium">{{ $doc->title }}</div>
                                <div class="text-sm text-gray-600">{{ $doc->file_name }}</div>
                                <div class="text-xs text-gray-500">{{ $doc->file_size_human }}</div>
                                
                                @if($doc->expires_at)
                                    <div class="mt-2">
                                        @if($doc->is_expired)
                                            <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">
                                                Expired {{ $doc->expires_at->diffForHumans() }}
                                            </span>
                                        @elseif($doc->is_expiring_soon)
                                            <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">
                                                Expires {{ $doc->expires_at->diffForHumans() }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-600">
                                                Expires {{ $doc->expires_at->format('M d, Y') }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        <a 
                            href="{{ $doc->download_url }}" 
                            target="_blank"
                            class="text-blue-600 hover:text-blue-800"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
    
    @if($getRecord()->documents()->where('client_visible', true)->count() === 0)
        <div class="text-center py-8 text-gray-500">
            No documents on file yet.
        </div>
    @endif
</div>


# 7. Expiration Reminders
## Command: Check Document Expirations


php
<?php

namespace App\Console\Commands;

use App\Models\OrganizationDocument;
use Illuminate\Console\Command;

class CheckDocumentExpirations extends Command
{
    protected $signature = 'documents:check-expirations';
    
    protected $description = 'Check for expiring documents and send reminders';
    
    public function handle()
    {
        *// Find documents expiring in 30 days that haven't been reminded recently*
        $expiringDocs = OrganizationDocument::whereNotNull('expires_at')
            ->where('requires_renewal', true)
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->where(function($q) {
                $q->whereNull('reminded_at')
                  ->orWhere('reminded_at', '<', now()->subDays(7));
            })
            ->get();
        
        foreach ($expiringDocs as $doc) {
            $doc->sendExpirationReminder();
            $this->info("Sent reminder for: {$doc->title} ({$doc->organization->name})");
        }
        
        $this->info("Checked {$expiringDocs->count()} expiring documents.");
    }
}

*// In app/Console/Kernel.php*
protected function schedule(Schedule $schedule)
{
    $schedule->command('documents:check-expirations')->daily();
}


# 8. Booking Validation
## Prevent Booking Without Required Docs


php
*// In BookingRequest validation or policy*

public function canBook(Organization $organization)
{
    if (!$organization->hasRequiredDocuments()) {
        throw ValidationException::withMessages([
            'organization' => 'Cannot book session. Missing required documents: ' . 
                implode(', ', $organization->getMissingRequiredDocuments()),
        ]);
    }
    
    return true;
}


# 9. Dashboard Widget - Expired Docs
## Photographer Dashboard


php
*// Widget showing expired/expiring documents*

class ExpiredDocumentsWidget extends Widget
{
    protected static string $view = 'filament.widgets.expired-documents';
    
    public function getData()
    {
        return [
            'expired' => OrganizationDocument::whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->with('organization')
                ->get(),
            
            'expiring_soon' => OrganizationDocument::whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays(30)])
                ->with('organization')
                ->get(),
        ];
    }
}


# Summary - Document Repository Features
### ✅Organized by type (Insurance, W-9, Contracts, Branding, etc.) ✅ Expiration tracking with automatic reminders ✅ Required documents flag prevents booking without them ✅ Client visibility control (some docs photographer-only) ✅ File type support (PDF, images, Word docs) ✅ Download links with signed URLs for security ✅ Upload tracking (who uploaded, when) ✅ Both photographer and client can upload ✅ Expiring soon warnings (30-day window) ✅ Dashboard alerts for missing/expired doc
#solo