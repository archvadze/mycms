<?php
namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Support\AdminAccess;
use App\Support\AuditLogFormatter;
use Filament\Actions;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;
    protected static ?string $navigationLabel = 'Activity Log';
    protected static ?int $navigationSort = 8;

    public static function canViewAny(): bool
    {
        return AdminAccess::canViewAudit();
    }

    public static function canView($record): bool
    {
        return AdminAccess::canViewAudit();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Operations';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Activity')
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Timestamp')
                        ->dateTime('M j, Y H:i:s'),
                    TextEntry::make('event')
                        ->placeholder('-'),
                    TextEntry::make('description')
                        ->columnSpanFull(),
                    TextEntry::make('subject')
                        ->state(fn(Activity $record): string => AuditLogFormatter::subjectLabel($record)),
                    TextEntry::make('causer')
                        ->state(fn(Activity $record): string => AuditLogFormatter::causerLabel($record)),
                ])
                ->columns(2),
            Section::make('Changed Fields')
                ->schema([
                    TextEntry::make('changed_fields')
                        ->state(fn(Activity $record): string => AuditLogFormatter::changedFields($record))
                        ->columnSpanFull(),
                ]),
            Section::make('Properties')
                ->schema([
                    KeyValueEntry::make('safe_properties')
                        ->state(fn(Activity $record): array => AuditLogFormatter::sanitizedProperties($record))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with(['causer', 'subject']))
            ->columns([
                TextColumn::make('causer.name')
                    ->label('User')
                    ->default('System')
                    ->searchable(),
                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('description')
                    ->label('Action')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('subject_type')
                    ->label('Model')
                    ->formatStateUsing(fn($state) => $state ? class_basename($state) : '-'),
                TextColumn::make('subject_id')
                    ->label('ID'),
                TextColumn::make('properties')
                    ->label('Details')
                    ->formatStateUsing(fn($state, Activity $record): string => AuditLogFormatter::changedFields($record))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->options(fn(): array => Activity::query()
                        ->whereNotNull('event')
                        ->distinct()
                        ->orderBy('event')
                        ->pluck('event', 'event')
                        ->all()),
                SelectFilter::make('subject_type')
                    ->label('Subject')
                    ->options(fn(): array => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->orderBy('subject_type')
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn(string $type): array => [$type => class_basename($type)])
                        ->all()),
                Filter::make('created_at')
                    ->schema([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ])
                    ->query(fn(Builder $query, array $data): Builder => $query
                        ->when(
                            $data['created_from'] ?? null,
                            fn(Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date)
                        )
                        ->when(
                            $data['created_until'] ?? null,
                            fn(Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date)
                        )),
            ])
            ->actions([
                Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
        ];
    }
}
