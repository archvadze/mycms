<?php
namespace App\Filament\Resources;

use App\Filament\Resources\BlogCommentResource\Pages;
use App\Models\Comment;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\AdminAccess;

class BlogCommentResource extends Resource
{
    protected static ?string $model = Comment::class;
    protected static ?string $navigationLabel = 'Comments';
    public static function getNavigationGroup(): ?string { return 'Content'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';
    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageSupportContent();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Comment::where('is_approved', false)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ["content", "user_name"];
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Comment Details')
                ->schema([
                    Forms\Components\Select::make('publication_id')
                        ->relationship('publication', 'title')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Select::make('user_id')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->label('User'),
                    Forms\Components\Toggle::make('is_approved')
                        ->label('Approved')
                        ->default(false),
                    Forms\Components\Textarea::make('content')
                        ->required()
                        ->rows(4)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('content')
                    ->label('Comment')
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\TextColumn::make('parent.user.name')
                    ->label('In Reply To')
                    ->default('—')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('replyToUser.name')
                    ->label('@Mention')
                    ->default('—')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('publication.title')
                    ->label('Post')
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_approved')
                    ->boolean()
                    ->label('Approved'),
                Tables\Columns\IconColumn::make('is_blocked')
                    ->boolean()
                    ->label('Blocked')
                    ->trueIcon('heroicon-o-no-symbol')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\IconColumn::make('parent_id')
                    ->label('Reply')
                    ->boolean()
                    ->getStateUsing(fn($record) => !is_null($record->parent_id)),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_blocked')
                    ->label('Blocked')
                    ->trueLabel('Blocked')
                    ->falseLabel('Not Blocked')
                    ->placeholder('All'),
                Tables\Filters\TernaryFilter::make('is_approved')
                    ->label('Status')
                    ->trueLabel('Approved')
                    ->falseLabel('Pending')
                    ->placeholder('All'),
            ])
            ->actions([
                Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Comment $record) => !$record->is_approved)
                    ->action(fn(Comment $record) => $record->update(['is_approved' => true])),
                Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Comment $record) => $record->is_approved)
                    ->action(fn(Comment $record) => $record->update(['is_approved' => false])),
                Actions\Action::make('block')
                    ->label('Block')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn(Comment $record) => !$record->is_blocked)
                    ->action(fn(Comment $record) => $record->update(['is_blocked' => true])),
                Actions\Action::make('unblock')
                    ->label('Unblock')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Comment $record) => $record->is_blocked)
                    ->action(fn(Comment $record) => $record->update(['is_blocked' => false])),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn($records) => $records->each->update(['is_approved' => true]))
                        ->requiresConfirmation(),
                    Actions\BulkAction::make('reject_selected')
                        ->label('Reject Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn($records) => $records->each->update(['is_approved' => false]))
                        ->requiresConfirmation(),
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBlogComments::route('/'),
            'create' => Pages\CreateBlogComment::route('/create'),
            'edit'   => Pages\EditBlogComment::route('/{record}/edit'),
        ];
    }
}
