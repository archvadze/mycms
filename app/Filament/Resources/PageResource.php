<?php
namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use App\Support\AdminAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;
    public static function getNavigationGroup(): ?string { return 'System'; }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Pages';

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageSystemContent();
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canManageSystemContent();
    }

    public static function statusOptions(): array
    {
        return ['draft' => 'Draft', 'published' => 'Published'];
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'published' => 'success',
            'draft' => 'warning',
            default => 'gray',
        };
    }

    public static function setPublished(Page $page, bool $published): void
    {
        if (! static::canEdit($page)) {
            throw new AuthorizationException();
        }

        $page->update(['status' => $published ? 'published' : 'draft']);
    }

    public static function previewUrl(Page $page): ?string
    {
        return match ($page->slug) {
            'home' => route('home'),
            'about' => route('about'),
            'contact' => route('contact'),
            'privacy-policy' => route('privacy-policy'),
            'terms' => route('terms'),
            'services' => route('services'),
            'portfolio' => route('portfolio'),
            'blog' => route('blog'),
            'guides' => route('guides'),
            'shop' => route('shop.index'),
            default => route('page.show', $page->slug),
        };
    }

    public static function form(Schema $schema): Schema
    {
        $record = $schema->getRecord();
        $slug = $record?->slug;

        $modulePages = ['blog', 'portfolio', 'services', 'guides', 'shop'];
        $contentPages = ['about', 'contact', 'privacy-policy', 'terms'];
        $isModule = in_array($slug, $modulePages);
        $isContent = in_array($slug, $contentPages);
        $isHome = $slug === 'home';
        $isContact = $slug === 'contact';

        $sections = [];

        // General
        $sections[] = Section::make('General')
            ->columnSpanFull()
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Admin Label')
                    ->helperText('მხოლოდ admin მენიუში ჩანს')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->disabled(fn ($record) => $record?->slug !== null),
                Forms\Components\Select::make('status')
                    ->options(static::statusOptions())
                    ->default('published')
                    ->required(),
            ])->columns(3);

        // SEO
        $sections[] = Section::make('SEO')
            ->columnSpanFull()
            ->schema([
                Forms\Components\TextInput::make('seo_title')->maxLength(255),
                Forms\Components\Textarea::make('seo_description')->maxLength(500)->rows(2),
            ])->columns(2)->collapsed();

        // Page Heading — ყველა გვერდზე
        $headingSchema = [
            Forms\Components\TextInput::make('page_title')
                ->label('Page Title')
                ->helperText('გვერდზე ჩასაჩვენებელი სათაური (h1)')
                ->maxLength(255),
            Forms\Components\Textarea::make('page_subtitle')
                ->label('Page Subtitle')
                ->helperText('გვერდის ქვესათაური')
                ->rows(2)
                ->maxLength(500),
        ];

        if ($isModule) {
            $headingSchema[] = Forms\Components\TextInput::make('items_count')
                ->label('Items to Show')
                ->helperText('რამდენი ელემენტი გამოჩნდეს გვერდზე')
                ->numeric()
                ->default(9)
                ->minValue(1)
                ->maxValue(50);
        }

        $sections[] = Section::make('Page Heading')
            ->columnSpanFull()
            ->description('სათაური და ქვესათაური რომელიც გვერდზე ჩანს')
            ->schema($headingSchema)
            ->columns(2);

        // Content — content pages + home
        if ($isContent || $isHome || !$isModule) {
            $sections[] = Section::make('Content')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\RichEditor::make('content'),
                ]);
        }

        // Contact fields
        if ($isContact) {
            $sections[] = Section::make('Contact Information')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('contact_phone')->label('Phone')->maxLength(255),
                    Forms\Components\TextInput::make('contact_email')->label('Email')->email()->maxLength(255),
                    Forms\Components\TextInput::make('working_hours')->label('Working Hours')->maxLength(255),
                    Forms\Components\Textarea::make('contact_address')->label('Address')->rows(2)->maxLength(500),
                ])->columns(2);

            $sections[] = Section::make('Google Maps')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Textarea::make('google_maps_embed')
                        ->label('Google Maps Embed Code')
                        ->rows(4)
                        ->placeholder('<iframe src="https://www.google.com/maps/embed?..." ...></iframe>'),
                ]);
        }


        // Frontpage Builder — მხოლოდ home
        if ($isHome) {
            $sections[] = Section::make('Frontpage Builder')
                ->columnSpanFull()
                ->description('მთავარ გვერდზე გამოსაჩენი სექციები')
                ->schema([
                    Forms\Components\Toggle::make('show_portfolio')
                        ->label('Show Portfolio Section')
                        ->default(true),
                    Forms\Components\Toggle::make('show_services')
                        ->label('Show Services Section')
                        ->default(true),
                    Forms\Components\Toggle::make('show_testimonials')
                        ->label('Show Testimonials Section')
                        ->default(true),
                    Forms\Components\Toggle::make('show_features')
                        ->label('Show Features Section')
                        ->default(true),
                    Forms\Components\Toggle::make('show_blog')
                        ->label('Show Blog Section')
                        ->default(false),
                ])->columns(2)->collapsed();

            $sections[] = Section::make('Features Section')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('features_title')->label('Title')->maxLength(255),
                    Forms\Components\Textarea::make('features_subtitle')->label('Subtitle')->rows(2)->maxLength(500),
                    Forms\Components\TextInput::make('features_items_count')->label('Items to Show')->numeric()->default(4)->minValue(1)->maxValue(12),
                ])->columns(3)->collapsed();

            $sections[] = Section::make('Section Order')
                ->columnSpanFull()
                ->description('სექციების თანმიმდევრობა (1 = პირველი)')
                ->schema([
                    Forms\Components\TextInput::make('section_order.portfolio')->label('Portfolio')->numeric()->default(1)->minValue(1)->maxValue(6),
                    Forms\Components\TextInput::make('section_order.services')->label('Services')->numeric()->default(2)->minValue(1)->maxValue(6),
                    Forms\Components\TextInput::make('section_order.features')->label('Features')->numeric()->default(3)->minValue(1)->maxValue(6),
                    Forms\Components\TextInput::make('section_order.testimonials')->label('Testimonials')->numeric()->default(4)->minValue(1)->maxValue(6),
                    Forms\Components\TextInput::make('section_order.blog')->label('Blog')->numeric()->default(5)->minValue(1)->maxValue(6),
                ])->columns(5)->collapsed();

            $sections[] = Section::make('Portfolio Section')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('portfolio_title')->label('Title')->maxLength(255),
                    Forms\Components\Textarea::make('portfolio_subtitle')->label('Subtitle')->rows(2)->maxLength(500),
                    Forms\Components\TextInput::make('portfolio_items_count')->label('Items to Show')->numeric()->default(6)->minValue(1)->maxValue(20),
                ])->columns(3)->collapsed();

            $sections[] = Section::make('Services Section')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('services_title')->label('Title')->maxLength(255),
                    Forms\Components\Textarea::make('services_subtitle')->label('Subtitle')->rows(2)->maxLength(500),
                    Forms\Components\TextInput::make('services_items_count')->label('Items to Show')->numeric()->default(6)->minValue(1)->maxValue(20),
                ])->columns(3)->collapsed();

            $sections[] = Section::make('Testimonials Section')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('testimonials_title')->label('Title')->maxLength(255),
                    Forms\Components\Textarea::make('testimonials_subtitle')->label('Subtitle')->rows(2)->maxLength(500),
                    Forms\Components\TextInput::make('testimonials_items_count')->label('Items to Show')->numeric()->default(3)->minValue(1)->maxValue(12),
                ])->columns(2)->collapsed();

            $sections[] = Section::make('Blog Section')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('blog_title')->label('Title')->maxLength(255),
                    Forms\Components\Textarea::make('blog_subtitle')->label('Subtitle')->rows(2)->maxLength(500),
                    Forms\Components\TextInput::make('blog_items_count')->label('Items to Show')->numeric()->default(3)->minValue(1)->maxValue(9),
                ])->columns(3)->collapsed();
        }

        // Hero Section — მხოლოდ home
        if ($isHome) {
            $sections[] = Section::make('Hero Section')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('hero_title')->maxLength(255)->columnSpanFull(),
                    Forms\Components\Textarea::make('hero_subtitle')->rows(2)->maxLength(500)->columnSpanFull(),
                    Forms\Components\Select::make('hero_media_type')
                        ->label('Media Type')
                        ->options(['image' => 'Image', 'video' => 'Video'])
                        ->default('image')
                        ->reactive()
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('hero_image')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->disk('public')
                        ->directory('pages')
                        ->label('Hero Image')
                        ->columnSpanFull()
                        ->hidden(fn($get) => $get('hero_media_type') === 'video'),
                    Forms\Components\FileUpload::make('hero_video')
                        ->disk('public')
                        ->directory('pages/videos')
                        ->label('Hero Video')
                        ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                        ->columnSpanFull()
                        ->hidden(fn($get) => $get('hero_media_type') !== 'video'),
                    Forms\Components\TextInput::make('hero_button_text')->maxLength(255),
                    Forms\Components\TextInput::make('hero_button_url')->maxLength(255),
                ])->columns(2)->collapsed();
        }

        return $schema->schema($sections);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('slug')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('page_title')->label('Page Title')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (?string $state): string => static::statusColor($state)),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(static::statusOptions()),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('preview')
                        ->label('Preview')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn(Page $record): ?string => static::previewUrl($record))
                        ->openUrlInNewTab()
                        ->visible(fn(Page $record): bool => $record->status === 'published'),
                    Actions\Action::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->authorize(fn(Page $record): bool => static::canEdit($record))
                        ->visible(fn(Page $record): bool => $record->status !== 'published')
                        ->action(fn(Page $record) => static::setPublished($record, true)),
                    Actions\Action::make('unpublish')
                        ->label('Unpublish')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->authorize(fn(Page $record): bool => static::canEdit($record))
                        ->visible(fn(Page $record): bool => $record->status === 'published')
                        ->action(fn(Page $record) => static::setPublished($record, false)),
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
                            fn(Page $record) => static::setPublished($record, true)
                        )),
                    Actions\BulkAction::make('unpublish')
                        ->label('Unpublish selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->action(fn($records): mixed => $records->each(
                            fn(Page $record) => static::setPublished($record, false)
                        )),
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
