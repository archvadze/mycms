<?php
namespace App\Filament\Resources;

use App\Filament\Resources\PublicationResource\Pages;
use App\Models\Publication;
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

class PublicationResource extends Resource
{
    protected static ?string $model = Publication::class;
    public static function getNavigationGroup(): ?string { return 'Content'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Publications';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageContent();
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canManageContent();
    }

    public static function statusOptions(): array
    {
        return ['draft'=>'Draft','published'=>'Published','archived'=>'Archived'];
    }

    public static function statusColor(?string $status): string
    {
        return match($status) {
            'published' => 'success',
            'draft' => 'warning',
            'archived' => 'gray',
            default => 'gray',
        };
    }

    public static function setPublished(Publication $publication, bool $published): void
    {
        if (! static::canEdit($publication)) {
            throw new AuthorizationException();
        }

        $publication->update([
            'is_published' => $published,
            'status' => $published ? 'published' : 'draft',
            'published_at' => $published
                ? ($publication->published_at ?? now())
                : $publication->published_at,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Publication Details')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                            if (blank($get('slug'))) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->unique(ignoreRecord: true)->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->options(static::statusOptions())
                        ->default('draft')->required(),
                    Forms\Components\DateTimePicker::make('published_at'),
                    Forms\Components\Toggle::make('is_published')->default(false),
                ])->columns(2),
            Section::make('Categories & Tags')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Select::make('categories')
                        ->relationship('categories', 'name')
                        ->multiple()->preload(),
                    Forms\Components\Select::make('tags')
                        ->relationship('tags', 'name')
                        ->multiple()->preload(),
                ])->columns(2),
            Section::make('Cover Image')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\FileUpload::make('cover_image')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->disk('public')->directory('publications'),
                ])->columns(2),
            Section::make('Excerpt')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Textarea::make('excerpt')
                        ->rows(3)->maxLength(500)->columnSpanFull()
                ]),
            Section::make('Content')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\RichEditor::make('content')
                        ->required()->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')->disk('public')->height(40)->width(60),
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn(?string $state): string => static::statusColor($state)),
                Tables\Columns\IconColumn::make('is_published')->boolean(),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(static::statusOptions()),
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No publications')
            ->emptyStateDescription('Blog posts and public articles will appear here.')
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('preview')
                        ->label('Preview')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn(Publication $record): string => route('blog.show', $record->slug))
                        ->openUrlInNewTab()
                        ->visible(fn(Publication $record): bool => (bool) $record->is_published),
                    Actions\Action::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->authorize(fn(Publication $record): bool => static::canEdit($record))
                        ->visible(fn(Publication $record): bool => ! $record->is_published)
                        ->action(fn(Publication $record) => static::setPublished($record, true)),
                    Actions\Action::make('unpublish')
                        ->label('Unpublish')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->authorize(fn(Publication $record): bool => static::canEdit($record))
                        ->visible(fn(Publication $record): bool => (bool) $record->is_published)
                        ->action(fn(Publication $record) => static::setPublished($record, false)),
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
                            fn(Publication $record) => static::setPublished($record, true)
                        )),
                    Actions\BulkAction::make('unpublish')
                        ->label('Unpublish selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->action(fn($records): mixed => $records->each(
                            fn(Publication $record) => static::setPublished($record, false)
                        )),
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPublications::route('/'),
            'create' => Pages\CreatePublication::route('/create'),
            'edit'   => Pages\EditPublication::route('/{record}/edit'),
        ];
    }
}
