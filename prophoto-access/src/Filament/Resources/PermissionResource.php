<?php

namespace ProPhoto\Access\Filament\Resources;

use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use ProPhoto\Access\Filament\Resources\PermissionResource\Pages;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-key';

    protected static string|null|\UnitEnum $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 2;

    public static function form(Form|\Filament\Schemas\Schema $form): \Filament\Schemas\Schema
    {
        return $form
            ->schema([
                Section::make('Permission Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Use snake_case (e.g., can_view_gallery)'),

                        Forms\Components\TextInput::make('guard_name')
                            ->default('web')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Assigned Roles')
                    ->schema([
                        Forms\Components\CheckboxList::make('roles')
                            ->relationship('roles', 'name')
                            ->columns(4)
                            ->gridDirection('row'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->description(fn (Permission $record): string => self::getPermissionDescription($record->name)),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->state(fn (Permission $record): string => self::getPermissionCategory($record->name))
                    ->color(fn (string $state): string => match ($state) {
                        'Gallery' => 'success',
                        'AI' => 'purple',
                        'Session' => 'info',
                        'Organization' => 'warning',
                        'Invoice' => 'danger',
                        'User' => 'gray',
                        'System' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('guard_name')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles_count')
                    ->label('Roles')
                    ->counts('roles')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Assigned To')
                    ->badge()
                    ->separator(',')
                    ->limitList(3)
                    ->expandableLimitedList(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'gallery' => 'Gallery',
                        'ai' => 'AI',
                        'session' => 'Session',
                        'organization' => 'Organization',
                        'invoice' => 'Invoice',
                        'user' => 'User',
                        'system' => 'System',
                    ])
                    ->query(function ($query, array $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'gallery' => $query->where('name', 'like', '%gallery%')->orWhere('name', 'like', '%image%'),
                            'ai' => $query->where('name', 'like', '%ai%'),
                            'session' => $query->where('name', 'like', '%session%')->orWhere('name', 'like', '%booking%')->orWhere('name', 'like', '%calendar%'),
                            'organization' => $query->where('name', 'like', '%organization%')->orWhere('name', 'like', '%org%'),
                            'invoice' => $query->where('name', 'like', '%invoice%')->orWhere('name', 'like', '%payment%'),
                            'user' => $query->where('name', 'like', '%user%')->orWhere('name', 'like', '%role%')->orWhere('name', 'like', '%permission%'),
                            'system' => $query->where('name', 'like', '%studio%')->orWhere('name', 'like', '%analytics%')->orWhere('name', 'like', '%integration%'),
                            default => $query,
                        };
                    }),

                Tables\Filters\SelectFilter::make('guard_name')
                    ->options([
                        'web' => 'Web',
                        'api' => 'API',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'view' => Pages\ViewPermission::route('/{record}'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }

    /**
     * Get permission category based on name
     */
    protected static function getPermissionCategory(string $name): string
    {
        return match (true) {
            str_contains($name, 'gallery') || str_contains($name, 'image') => 'Gallery',
            str_contains($name, 'ai') => 'AI',
            str_contains($name, 'session') || str_contains($name, 'booking') || str_contains($name, 'calendar') => 'Session',
            str_contains($name, 'organization') || str_contains($name, 'org_') => 'Organization',
            str_contains($name, 'invoice') || str_contains($name, 'payment') || str_contains($name, 'stripe') => 'Invoice',
            str_contains($name, 'user') || str_contains($name, 'role') || str_contains($name, 'permission') || str_contains($name, 'invite') => 'User',
            str_contains($name, 'studio') || str_contains($name, 'analytics') || str_contains($name, 'integration') || str_contains($name, 'staging') => 'System',
            default => 'Other',
        };
    }

    /**
     * Get human-readable description for permission
     */
    protected static function getPermissionDescription(string $name): string
    {
        $descriptions = [
            'can_create_gallery' => 'Create new photo galleries',
            'can_view_gallery' => 'View galleries and images',
            'can_edit_gallery' => 'Edit gallery details',
            'can_delete_gallery' => 'Delete galleries',
            'can_archive_gallery' => 'Archive completed galleries',
            'can_upload_images' => 'Upload images to galleries',
            'can_delete_images' => 'Delete images from galleries',
            'can_download_images' => 'Download images',
            'can_share_gallery' => 'Share gallery with others',
            'can_approve_images' => 'Approve images for marketing',
            'can_rate_images' => 'Rate images with stars',
            'can_comment_on_images' => 'Add comments to images',
            'can_request_edits' => 'Request edits on images',
            'can_mark_gallery_complete' => 'Mark gallery as complete',
            'can_enable_ai_portraits' => 'Enable AI portrait generation',
            'can_train_ai_model' => 'Train AI models',
            'can_generate_ai_portraits' => 'Generate AI portraits',
            'can_view_ai_portraits' => 'View AI portraits',
            'can_download_ai_portraits' => 'Download AI portraits',
            'can_disable_ai_portraits' => 'Disable AI for subjects',
            'can_view_ai_costs' => 'View AI generation costs',
            'can_create_session' => 'Create photo sessions',
            'can_view_session' => 'View session details',
            'can_edit_session' => 'Edit session details',
            'can_delete_session' => 'Delete sessions',
            'can_request_booking' => 'Request session bookings',
            'can_confirm_booking' => 'Confirm booking requests',
            'can_deny_booking' => 'Deny booking requests',
            'can_cancel_session' => 'Cancel sessions',
            'can_view_calendar' => 'View availability calendar',
            'can_create_organization' => 'Create organizations',
            'can_view_organization' => 'View organization details',
            'can_edit_organization' => 'Edit organization details',
            'can_delete_organization' => 'Delete organizations',
            'can_manage_org_users' => 'Manage organization users',
            'can_manage_org_settings' => 'Manage organization settings',
            'can_create_invoice' => 'Create invoices',
            'can_view_invoice' => 'View invoices',
            'can_edit_invoice' => 'Edit invoices',
            'can_delete_invoice' => 'Delete invoices',
            'can_send_invoice' => 'Send invoices to clients',
            'can_record_payment' => 'Record payments',
            'can_view_payment_history' => 'View payment history',
            'can_export_invoice_pdf' => 'Export invoices as PDF',
            'can_manage_stripe' => 'Manage Stripe integration',
            'can_create_user' => 'Create new users',
            'can_view_user' => 'View user profiles',
            'can_edit_user' => 'Edit user profiles',
            'can_delete_user' => 'Delete users',
            'can_assign_roles' => 'Assign roles to users',
            'can_manage_permissions' => 'Manage user permissions',
            'can_invite_users' => 'Invite new users',
            'can_send_message' => 'Send messages',
            'can_view_messages' => 'View messages',
            'can_delete_message' => 'Delete messages',
            'can_manage_notifications' => 'Manage notifications',
            'can_manage_studio_settings' => 'Manage studio settings',
            'can_view_analytics' => 'View analytics dashboard',
            'can_manage_integrations' => 'Manage third-party integrations',
            'can_access_staging' => 'Access staging area',
            'can_view_all_data' => 'View all data across studio',
        ];

        return $descriptions[$name] ?? 'No description available';
    }
}
