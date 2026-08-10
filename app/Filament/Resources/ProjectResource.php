<?php
namespace App\Filament\Resources;
use App\Filament\Resources\ProjectResource\Pages;
use App\Enums\ProjectStatus;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Project;
use Filament\Forms;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\AdminAccess;
use App\Support\AuditLogFormatter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;
    public static function getNavigationGroup(): ?string { return 'Operations'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-folder';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageProjects();
    }

    public static function canCreate(): bool
    {
        return AdminAccess::canManageProjects();
    }

    public static function canView(Model $record): bool
    {
        return AdminAccess::canManageProjects();
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canManageProjects();
    }

    public static function canDelete(Model $record): bool
    {
        return AdminAccess::canManageProjects()
            && $record instanceof Project
            && $record->order_id === null;
    }

    public static function canDeleteAny(): bool
    {
        return AdminAccess::canManageProjects();
    }

    protected static ?string $navigationLabel = 'Client Projects';

    public static function statusOptions(): array
    {
        return collect(ProjectStatus::cases())
            ->mapWithKeys(fn(ProjectStatus $status): array => [
                $status->value => $status->label(),
            ])
            ->all();
    }

    public static function statusLabel(?string $status): string
    {
        return ProjectStatus::tryFrom((string) $status)?->label()
            ?? ucfirst((string) $status);
    }

    public static function statusColor(?string $status): string
    {
        return ProjectStatus::tryFrom((string) $status)?->color() ?? 'gray';
    }

    public static function canTransitionStatus(Project $project, string $status): bool
    {
        return array_key_exists($status, static::statusOptions())
            && $project->status !== $status;
    }

    /**
     * @throws AuthorizationException
     */
    public static function updateStatus(Project $project, string $status): void
    {
        if (! array_key_exists($status, static::statusOptions())) {
            throw new InvalidArgumentException("Unsupported project status [{$status}].");
        }

        if (! static::canEdit($project)) {
            throw new AuthorizationException();
        }

        if ($project->status === $status) {
            return;
        }

        $project->update(['status' => $status]);
    }

    public static function normalizeFormDataForPersistence(array $data, ?Project $record = null): array
    {
        if (! array_key_exists($data['status'] ?? '', static::statusOptions())) {
            throw ValidationException::withMessages([
                'data.status' => 'Unsupported project status.',
            ]);
        }

        if ($record?->order_id !== null) {
            $submittedOrderId = array_key_exists('order_id', $data)
                ? $data['order_id']
                : $record->order_id;

            if ((int) $submittedOrderId !== (int) $record->order_id) {
                throw ValidationException::withMessages([
                    'data.order_id' => 'The source order for an existing linked project cannot be changed.',
                ]);
            }

            $data['order_id'] = $record->order_id;
        }

        static::validateOrderClientConsistency($data);

        return $data;
    }

    protected static function validateOrderClientConsistency(array $data): void
    {
        if (blank($data['order_id'] ?? null)) {
            return;
        }

        $order = Order::query()
            ->select(['id', 'client_id'])
            ->find($data['order_id']);

        if (! $order || (int) $order->client_id !== (int) ($data['client_id'] ?? 0)) {
            throw ValidationException::withMessages([
                'data.order_id' => 'The selected source order must belong to the selected client.',
            ]);
        }
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Project Info')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('client_id')
                        ->relationship('client', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('order_id')
                        ->label('Source order')
                        ->relationship('order', 'id')
                        ->getOptionLabelFromRecordUsing(fn($record): string => '#' . $record->id . ' - ' . $record->client_name)
                        ->searchable()
                        ->preload()
                        ->disabled(fn(?Project $record): bool => $record?->order_id !== null)
                        ->helperText('Existing source order links cannot be changed.'),
                    Forms\Components\Select::make('status')
                        ->options(static::statusOptions())
                        ->default('pending')
                        ->required(),
                    Forms\Components\TextInput::make('progress')
                        ->label('Progress (%)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->helperText('Set project completion percentage (0-100)'),
                    Forms\Components\TextInput::make('price')
                        ->numeric()
                        ->prefix('$'),
                    Forms\Components\DatePicker::make('deadline'),
                ])->columns(2),
            Section::make('Description')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->rows(4)
                        ->columnSpanFull()
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Project')
                ->schema([
                    TextEntry::make('title')
                        ->label('Title'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn(?string $state): string => static::statusLabel($state))
                        ->color(fn(?string $state): string => static::statusColor($state)),
                    TextEntry::make('progress')
                        ->label('Progress')
                        ->formatStateUsing(fn($state): string => (int) $state . '%'),
                    TextEntry::make('price')
                        ->money('USD')
                        ->placeholder('-'),
                    TextEntry::make('deadline')
                        ->date()
                        ->placeholder('-'),
                    TextEntry::make('description')
                        ->columnSpanFull()
                        ->placeholder('No description'),
                ])
                ->columns(2),
            Section::make('Client')
                ->schema([
                    TextEntry::make('client.name')
                        ->label('Name')
                        ->url(fn(Project $record): ?string =>
                            $record->client && ClientResource::canViewAny()
                                ? ClientResource::getUrl('edit', ['record' => $record->client])
                                : null
                        ),
                    TextEntry::make('client.email')
                        ->label('Email')
                        ->copyable()
                        ->placeholder('-'),
                    TextEntry::make('client.phone')
                        ->label('Phone')
                        ->placeholder('-'),
                    TextEntry::make('client.company')
                        ->label('Company')
                        ->placeholder('-'),
                ])
                ->columns(2),
            Section::make('Source Order')
                ->schema([
                    TextEntry::make('order.id')
                        ->label('Order')
                        ->formatStateUsing(fn($state): string => $state ? '#' . $state : '-')
                        ->url(fn(Project $record): ?string =>
                            $record->order && OrderResource::canViewAny()
                                ? OrderResource::getUrl('view', ['record' => $record->order])
                                : null
                        ),
                    TextEntry::make('order.status')
                        ->label('Order status')
                        ->badge()
                        ->formatStateUsing(fn(?string $state): string => $state ? OrderResource::statusLabel($state) : '-')
                        ->color(fn(?string $state): string => $state ? OrderResource::statusColor($state) : 'gray'),
                    TextEntry::make('order.price_estimate')
                        ->label('Order estimate')
                        ->money('USD')
                        ->placeholder('-'),
                    TextEntry::make('order.created_at')
                        ->label('Order created')
                        ->dateTime('M j, Y H:i')
                        ->placeholder('-'),
                ])
                ->columns(2)
                ->visible(fn(Project $record): bool => $record->order !== null),
            Section::make('Operational')
                ->schema([
                    TextEntry::make('created_at')->dateTime('M j, Y H:i'),
                    TextEntry::make('updated_at')->dateTime('M j, Y H:i'),
                ])
                ->columns(2),
            Section::make('Recent Activity')
                ->schema([
                    RepeatableEntry::make('activity')
                        ->label('')
                        ->state(fn(Project $record): array => Activity::query()
                            ->with('causer')
                            ->where('subject_type', Project::class)
                            ->where('subject_id', $record->id)
                            ->latest()
                            ->limit(10)
                            ->get()
                            ->map(fn(Activity $activity): array => [
                                'actor' => AuditLogFormatter::causerLabel($activity),
                                'event' => $activity->event ?? '-',
                                'description' => $activity->description,
                                'changes' => AuditLogFormatter::changedFields($activity),
                                'time' => $activity->created_at?->format('M j, Y H:i'),
                            ])
                            ->all())
                        ->schema([
                            TextEntry::make('time')->label('Time'),
                            TextEntry::make('actor')->label('Actor'),
                            TextEntry::make('event')->label('Event')->badge(),
                            TextEntry::make('description')->label('Action'),
                            TextEntry::make('changes')->label('Changed')->columnSpanFull(),
                        ])
                        ->columns(4)
                        ->columnSpanFull(),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with(['client', 'order']))
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(32),
                Tables\Columns\TextColumn::make('client.name')->sortable()->searchable()->limit(26),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn(?string $state): string => static::statusLabel($state))
                    ->color(fn(?string $state): string => static::statusColor($state)),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Order')
                    ->formatStateUsing(fn($state): string => $state ? '#' . $state : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('progress')
                    ->label('Progress')
                    ->formatStateUsing(fn($state) => $state . '%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')->money('USD'),
                Tables\Columns\TextColumn::make('deadline')->date()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->since()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(static::statusOptions()),
                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('order_id')
                    ->label('Linked order')
                    ->nullable(),
                Tables\Filters\Filter::make('deadline')
                    ->schema([
                        Forms\Components\DatePicker::make('deadline_from')
                            ->label('Deadline from'),
                        Forms\Components\DatePicker::make('deadline_until')
                            ->label('Deadline until'),
                    ])
                    ->query(fn(Builder $query, array $data): Builder => $query
                        ->when(
                            $data['deadline_from'] ?? null,
                            fn(Builder $query, string $date): Builder => $query->whereDate('deadline', '>=', $date)
                        )
                        ->when(
                            $data['deadline_until'] ?? null,
                            fn(Builder $query, string $date): Builder => $query->whereDate('deadline', '<=', $date)
                        )),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    ...array_map(
                        fn(ProjectStatus $status): Actions\Action => static::statusAction($status),
                        ProjectStatus::cases()
                    ),
                    Actions\DeleteAction::make()
                        ->visible(fn(Project $record): bool => static::canDelete($record)),
                ])->label('Actions')->icon('heroicon-m-ellipsis-vertical')->iconButton(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('delete')
                        ->label('Delete selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->authorize(fn(): bool => static::canDeleteAny())
                        ->action(fn(Collection $records): mixed => $records->each(
                            fn(Project $record) => static::canDelete($record) ? $record->delete() : null
                        )),
                ]),
            ]);
    }

    public static function statusAction(ProjectStatus $status): Actions\Action
    {
        return Actions\Action::make('status_' . $status->value)
            ->label('Mark ' . $status->label())
            ->icon('heroicon-o-arrow-path')
            ->color($status->color())
            ->authorize(fn(Project $record): bool => static::canEdit($record))
            ->visible(fn(Project $record): bool => static::canEdit($record) && static::canTransitionStatus($record, $status->value))
            ->action(fn(Project $record) => static::updateStatus($record, $status->value))
            ->successNotificationTitle('Project marked ' . strtolower($status->label()));
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ProjectResource\RelationManagers\MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view'   => Pages\ViewProject::route('/{record}'),
            'edit'   => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
