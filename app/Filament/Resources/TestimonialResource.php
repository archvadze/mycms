<?php
namespace App\Filament\Resources;

use App\Filament\Resources\TestimonialResource\Pages;
use App\Models\Testimonial;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\AdminAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;
    public static function getNavigationGroup(): ?string { return 'Content'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-oval-left';
    protected static ?string $navigationLabel = 'Testimonials';
    protected static ?int $navigationSort = 8;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageContent();
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canManageContent();
    }

    public static function setPublished(Testimonial $testimonial, bool $published): void
    {
        if (! static::canEdit($testimonial)) {
            throw new AuthorizationException();
        }

        $testimonial->update(['is_published' => $published]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Client Info')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('client_name')
                        ->required()->maxLength(255),
                    Forms\Components\TextInput::make('client_position')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('company')
                        ->maxLength(255),
                    Forms\Components\Select::make('rating')
                        ->options([1=>'⭐',2=>'⭐⭐',3=>'⭐⭐⭐',4=>'⭐⭐⭐⭐',5=>'⭐⭐⭐⭐⭐'])
                        ->default(5)
                ])->columns(2),
            Section::make('Photo')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\FileUpload::make('photo')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->disk('public')
                        ->directory('testimonials')
                        ->columnSpanFull(),
                ]),
            Section::make('Testimonial')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Textarea::make('testimonial_text')
                        ->required()->rows(4)->columnSpanFull(),
                    Forms\Components\Toggle::make('is_featured')->default(false),
                    Forms\Components\Toggle::make('is_published')->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo')->circular()->height(40),
                Tables\Columns\TextColumn::make('client_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('company'),
                Tables\Columns\TextColumn::make('testimonial_text')->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('rating')->badge()->color('warning')
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
                    Actions\Action::make('preview')
                        ->label('Preview')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(route('testimonials'))
                        ->openUrlInNewTab()
                        ->visible(fn(Testimonial $record): bool => (bool) $record->is_published),
                    Actions\Action::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->authorize(fn(Testimonial $record): bool => static::canEdit($record))
                        ->visible(fn(Testimonial $record): bool => ! $record->is_published)
                        ->action(fn(Testimonial $record) => static::setPublished($record, true)),
                    Actions\Action::make('unpublish')
                        ->label('Unpublish')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->authorize(fn(Testimonial $record): bool => static::canEdit($record))
                        ->visible(fn(Testimonial $record): bool => (bool) $record->is_published)
                        ->action(fn(Testimonial $record) => static::setPublished($record, false)),
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
                            fn(Testimonial $record) => static::setPublished($record, true)
                        )),
                    Actions\BulkAction::make('unpublish')
                        ->label('Unpublish selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->action(fn($records): mixed => $records->each(
                            fn(Testimonial $record) => static::setPublished($record, false)
                        )),
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTestimonials::route('/'),
            'create' => Pages\CreateTestimonial::route('/create'),
            'edit'   => Pages\EditTestimonial::route('/{record}/edit'),
        ];
    }
}
