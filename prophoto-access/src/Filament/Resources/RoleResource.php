<?php

namespace ProPhoto\Access\Filament\Resources;

use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use ProPhoto\Access\Filament\Resources\RoleResource\Pages;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-shield-check';

    protected static string|null|\UnitEnum $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 1;

    public static function form(Form|\Filament\Schemas\Schema $form): \Filament\Schemas\Schema
    {
        return $form
            ->schema([
                Section::make('Role Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Use snake_case (e.g., studio_user, client_user)'),

                        Forms\Components\TextInput::make('guard_name')
                            ->default('web')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Permissions')
                    ->description('Select the permissions this role should have')
                    ->schema([
                        Tabs::make('Permission Categories')
                            ->tabs([
                                Tab::make('Galleries')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::query()
                                                    ->where('name', 'like', '%gallery%')
                                                    ->orWhere('name', 'like', '%image%')
                                                    ->pluck('name', 'id')
                                            )
                                            ->columns(2)
                                            ->gridDirection('row'),
                                    ]),

                                Tab::make('AI Portraits')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::query()
                                                    ->where('name', 'like', '%ai%')
                                                    ->pluck('name', 'id')
                                            )
                                            ->columns(2)
                                            ->gridDirection('row'),
                                    ]),

                                Tab::make('Sessions & Bookings')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::query()
                                                    ->where('name', 'like', '%session%')
                                                    ->orWhere('name', 'like', '%booking%')
                                                    ->orWhere('name', 'like', '%calendar%')
                                                    ->pluck('name', 'id')
                                            )
                                            ->columns(2)
                                            ->gridDirection('row'),
                                    ]),

                                Tab::make('Organizations')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::query()
                                                    ->where('name', 'like', '%organization%')
                                                    ->orWhere('name', 'like', '%org%')
                                                    ->pluck('name', 'id')
                                            )
                                            ->columns(2)
                                            ->gridDirection('row'),
                                    ]),

                                Tab::make('Invoices')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::query()
                                                    ->where('name', 'like', '%invoice%')
                                                    ->orWhere('name', 'like', '%payment%')
                                                    ->orWhere('name', 'like', '%stripe%')
                                                    ->pluck('name', 'id')
                                            )
                                            ->columns(2)
                                            ->gridDirection('row'),
                                    ]),

                                Tab::make('Users & System')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::query()
                                                    ->where('name', 'like', '%user%')
                                                    ->orWhere('name', 'like', '%role%')
                                                    ->orWhere('name', 'like', '%permission%')
                                                    ->orWhere('name', 'like', '%studio%')
                                                    ->orWhere('name', 'like', '%analytics%')
                                                    ->orWhere('name', 'like', '%integration%')
                                                    ->orWhere('name', 'like', '%staging%')
                                                    ->orWhere('name', 'like', '%message%')
                                                    ->orWhere('name', 'like', '%notification%')
                                                    ->pluck('name', 'id')
                                            )
                                            ->columns(2)
                                            ->gridDirection('row'),
                                    ]),

                                Tab::make('All Permissions')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->relationship('permissions', 'name')
                                            ->searchable()
                                            ->columns(3)
                                            ->gridDirection('row'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'studio_user' => 'success',
                        'client_user' => 'info',
                        'guest_user' => 'warning',
                        'vendor_user' => 'gray',
                        default => 'primary',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('guard_name')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
            ]);
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
