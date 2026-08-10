<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PortfolioProjectResource\Pages;
use App\Models\PortfolioProject;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\AdminAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PortfolioProjectResource extends Resource
{
    protected static ?string $model = PortfolioProject::class;
    public static function getNavigationGroup(): ?string { return 'Operations'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';
    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageContent();
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canManageContent();
    }

    protected static ?string $navigationLabel = 'Portfolio';

    public static function setPublished(PortfolioProject $project, bool $published): void
    {
        if (! static::canEdit($project)) {
            throw new AuthorizationException();
        }

        $project->update(['is_published' => $published]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Project Details')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                            if (blank($get('slug'))) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('client_id')
                        ->relationship('client', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('project_type')
                        ->options([
                            'website'    => 'Website',
                            'e-commerce' => 'E-commerce',
                            'web-app'    => 'Web Application',
                            'mobile-app' => 'Mobile App',
                            'branding'   => 'Branding',
                        ]),
                    Forms\Components\DatePicker::make('completed_at')
                        ->label('Completed At'),
                    Forms\Components\TextInput::make('project_url')
                        ->label('Project URL')
                        ->url()
                        ->maxLength(255),
                ])->columns(2),
            Section::make('Cover Image')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\FileUpload::make('cover_image')
                        ->label('Cover Image')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->disk('public')->directory('portfolio')
                        ->imageResizeMode('cover')
                        ->imageCropAspectRatio('16:9')
                        ->imageResizeTargetWidth('1200')
                        ->imageResizeTargetHeight('675')
                        ->columnSpanFull(),
                ]),
            Section::make('Description & Technologies')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\TagsInput::make('technologies')
                        ->placeholder('Laravel, Vue.js, MySQL...')
                        ->columnSpanFull(),
                ]),
            Section::make('Visibility')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Toggle::make('is_featured')
                        ->label('Featured on Homepage')
                        ->default(false),
                    Forms\Components\Toggle::make('is_published')
                        ->label('Published')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')
                    ->label('Cover')
                    ->height(50)
                    ->width(80),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('client.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('project_type')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('completed_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_featured')->boolean(),
                Tables\Columns\IconColumn::make('is_published')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->authorize(fn(PortfolioProject $record): bool => static::canEdit($record))
                        ->visible(fn(PortfolioProject $record): bool => ! $record->is_published)
                        ->action(fn(PortfolioProject $record) => static::setPublished($record, true)),
                    Actions\Action::make('unpublish')
                        ->label('Unpublish')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->authorize(fn(PortfolioProject $record): bool => static::canEdit($record))
                        ->visible(fn(PortfolioProject $record): bool => (bool) $record->is_published)
                        ->action(fn(PortfolioProject $record) => static::setPublished($record, false)),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make(),
                ])->label('Actions')->icon('heroicon-m-ellipsis-vertical')->iconButton(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('publish')
                        ->label('Publish selected')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(fn($records): mixed => $records->each(
                            fn(PortfolioProject $record) => static::setPublished($record, true)
                        )),
                    Actions\BulkAction::make('unpublish')
                        ->label('Unpublish selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->action(fn($records): mixed => $records->each(
                            fn(PortfolioProject $record) => static::setPublished($record, false)
                        )),
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPortfolioProjects::route('/'),
            'create' => Pages\CreatePortfolioProject::route('/create'),
            'edit'   => Pages\EditPortfolioProject::route('/{record}/edit'),
        ];
    }
}
