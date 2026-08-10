<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DigitalProductVersionResource\Pages;
use App\Models\DigitalProductVersion;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\AdminAccess;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DigitalProductVersionResource extends Resource
{
    protected static ?string $model = DigitalProductVersion::class;

    private const ALLOWED_FILE_MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'text/plain' => 'txt',
    ];

    private const DANGEROUS_ORIGINAL_EXTENSIONS = [
        'bash',
        'phar',
        'php',
        'phtml',
        'sh',
    ];

    public static function getNavigationGroup(): ?string { return 'Content'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'Product Versions';
    protected static ?int $navigationSort = 7;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageContent() || AdminAccess::canManageOrders();
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof DigitalProductVersion
            && static::canViewAny()
            && ! $record->purchases()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function fileUploadDirectory(?int $productId): string
    {
        return 'digital-products/' . ($productId ?: 'unassigned');
    }

    public static function safeStoredFilename(string $extension): string
    {
        $extension = Str::lower(trim($extension, '.'));

        return (string) Str::uuid() . ($extension ? ".{$extension}" : '');
    }

    public static function safeStoredFilenameForUpload($file): string
    {
        return static::safeStoredFilename(static::validatedServerExtension($file) ?? '');
    }

    public static function validateUploadFilename($file): ?string
    {
        if (! is_object($file)) {
            return null;
        }

        if (static::hasDangerousOriginalExtension($file)) {
            return 'This file extension is not allowed.';
        }

        return static::validatedServerExtension($file) === null
            ? 'This file type is not allowed.'
            : null;
    }

    private static function validatedServerExtension($file): ?string
    {
        if (! is_object($file) || ! method_exists($file, 'getMimeType')) {
            return null;
        }

        $mime = $file->getMimeType();

        return is_string($mime) ? (self::ALLOWED_FILE_MIME_EXTENSIONS[$mime] ?? null) : null;
    }

    private static function hasDangerousOriginalExtension($file): bool
    {
        if (! is_object($file) || ! method_exists($file, 'getClientOriginalExtension')) {
            return false;
        }

        return in_array(
            Str::lower($file->getClientOriginalExtension()),
            self::DANGEROUS_ORIGINAL_EXTENSIONS,
            true
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Version Details')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Select::make('digital_product_id')
                        ->label('Product')
                        ->relationship('product', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('version_number')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('1.0.0'),
                    Forms\Components\Textarea::make('changelog')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(false),
                ])->columns(2),
            Section::make('File')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\FileUpload::make('file_path')
                        ->label('Product File')
                        ->disk('local')
                        ->directory(fn(Get $get): string => static::fileUploadDirectory(
                            $get('digital_product_id') ? (int) $get('digital_product_id') : null
                        ))
                        ->getUploadedFileNameForStorageUsing(
                            fn($file): string => static::safeStoredFilenameForUpload($file)
                        )
                        ->acceptedFileTypes(array_keys(self::ALLOWED_FILE_MIME_EXTENSIONS))
                        ->rules([
                            fn(): Closure => function (string $attribute, $value, Closure $fail): void {
                                $error = static::validateUploadFilename($value);

                                if ($error !== null) {
                                    $fail($error);
                                }
                            },
                        ])
                        ->maxSize(10240)
                        ->previewable(false)
                        ->downloadable(false)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with('product'))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('version_number')
                    ->label('Version')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('digital_product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No product versions')
            ->emptyStateDescription('Private product files are managed as versions here.')
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->authorize(fn(DigitalProductVersion $record): bool => static::canDelete($record))
                    ->visible(fn(DigitalProductVersion $record): bool => static::canDelete($record)),
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
                            fn(DigitalProductVersion $record) => static::canDelete($record) ? $record->delete() : null
                        )),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDigitalProductVersions::route('/'),
            'create' => Pages\CreateDigitalProductVersion::route('/create'),
            'edit'   => Pages\EditDigitalProductVersion::route('/{record}/edit'),
        ];
    }
}
