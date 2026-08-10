<?php
namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use App\Models\User;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\AdminAccess;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;
    public static function getNavigationGroup(): ?string { return 'Operations'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Clients';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageClients();
    }

    private static function countries(): array
    {
        return [
            'Georgia' => 'Georgia', 'United States' => 'United States',
            'United Kingdom' => 'United Kingdom', 'Germany' => 'Germany',
            'France' => 'France', 'Turkey' => 'Turkey', 'Armenia' => 'Armenia',
            'Azerbaijan' => 'Azerbaijan', 'Russia' => 'Russia', 'Ukraine' => 'Ukraine',
            'Poland' => 'Poland', 'Netherlands' => 'Netherlands', 'Spain' => 'Spain',
            'Italy' => 'Italy', 'Canada' => 'Canada', 'Australia' => 'Australia',
            'UAE' => 'UAE', 'Israel' => 'Israel', 'Sweden' => 'Sweden',
            'Norway' => 'Norway', 'Denmark' => 'Denmark', 'Finland' => 'Finland',
            'Switzerland' => 'Switzerland', 'Austria' => 'Austria', 'Belgium' => 'Belgium',
            'Portugal' => 'Portugal', 'Greece' => 'Greece', 'Romania' => 'Romania',
            'Bulgaria' => 'Bulgaria', 'Czech Republic' => 'Czech Republic',
            'Hungary' => 'Hungary', 'Slovakia' => 'Slovakia', 'Croatia' => 'Croatia',
            'Serbia' => 'Serbia', 'Other' => 'Other',
        ];
    }

    private static function interests(): array
    {
        return [
            'Technology' => 'Technology', 'Design' => 'Design',
            'Business' => 'Business', 'Marketing' => 'Marketing',
            'E-commerce' => 'E-commerce', 'Education' => 'Education',
            'Healthcare' => 'Healthcare', 'Finance' => 'Finance',
            'Real Estate' => 'Real Estate', 'Travel' => 'Travel',
            'Food & Restaurant' => 'Food & Restaurant', 'Fashion' => 'Fashion',
            'Sports' => 'Sports', 'Entertainment' => 'Entertainment',
            'Non-profit' => 'Non-profit', 'Government' => 'Government',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Personal Info')
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('email')->email()->required()->maxLength(255),
                    Forms\Components\TextInput::make('phone')->tel()->maxLength(255),
                    Forms\Components\Select::make('country')
                        ->options(self::countries())
                        ->searchable(),
                    Forms\Components\DatePicker::make('birthday')->label('Birthday'),
                    Forms\Components\Select::make('status')
                        ->options([
                            'lead'     => '🎯 Lead',
                            'active'   => '✅ Active',
                            'vip'      => '⭐ VIP',
                            'inactive' => '💤 Inactive',
                        ])
                        ->default('active')->required(),
                ])->columns(2)->columnSpanFull(),
            Section::make('Business Info')
                ->schema([
                    Forms\Components\TextInput::make('company')->maxLength(255),
                    Forms\Components\TextInput::make('website')->url()->maxLength(255)->prefix('https://'),
                    Forms\Components\TextInput::make('social_linkedin')->label('LinkedIn')->url()->maxLength(255),
                    Forms\Components\TextInput::make('social_facebook')->label('Facebook')->url()->maxLength(255),
                ])->columns(2)->columnSpanFull(),
            Section::make('Avatar & Interests')
                ->schema([
                    Forms\Components\ViewField::make('avatar_preview')
                        ->label('Avatar')
                        ->view('filament.components.avatar-preview'),
                    Forms\Components\Select::make('user.tags')
                        ->label('Interests')
                        ->options(self::interests())
                        ->multiple()
                        ->searchable(),
                ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('user.avatar')
                    ->label('')
                    ->disk('public')
                    ->getStateUsing(fn($record) => $record->user?->avatar
                        ? 'avatars/' . $record->user->avatar
                        : null)
                    ->defaultImageUrl(asset('avatars/default.svg'))
                    ->circular()
                    ->width(36)->height(36),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn(string $state): string => match($state) {
                        'vip'      => 'warning',
                        'active'   => 'success',
                        'lead'     => 'info',
                        'inactive' => 'gray',
                        default    => 'gray',
                    }),
                Tables\Columns\TextColumn::make('company')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('country')->toggleable(),
                Tables\Columns\TextColumn::make('orders_count')->label('Orders')->counts('orders')->sortable(),
                Tables\Columns\TextColumn::make('projects_count')->label('Projects')->counts('projects')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('M j, Y')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'lead' => 'Lead', 'active' => 'Active',
                        'vip' => 'VIP', 'inactive' => 'Inactive',
                    ]),
                Tables\Filters\SelectFilter::make('country')
                    ->options(self::countries()),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit'   => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
