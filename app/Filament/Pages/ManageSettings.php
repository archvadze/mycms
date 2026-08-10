<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Services\MailSettings;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use App\Support\AdminAccess;
use App\Support\AdminAudit;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    public static function canAccess(): bool
    {
        return AdminAccess::canManageSettings();
    }
    protected static ?string $navigationLabel = 'Settings';
    protected static ?string $title = 'Site Settings';
    protected static ?int $navigationSort = 6;
    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public function getView(): string
    {
        return 'filament.pages.manage-settings';
    }

    public ?array $data = [];

    public function mount(): void
    {
        $settings = SiteSetting::pluck('value', 'key')->toArray();
        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('General')
                    ->schema([
                        Forms\Components\TextInput::make('site_name')
                            ->label('Site Name')
                            ->placeholder(config('agency.name'))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('site_url')
                            ->label('Site URL')
                            ->placeholder(config('agency.url'))
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('color_primary')
                            ->label('Primary Color (HSL)')
                            ->placeholder('221 83% 53%')
                            ->helperText('HSL format: H S% L%')
                            ->suffixIcon('heroicon-o-swatch'),
                    ])->columns(2),

                Section::make('Branding')
                    ->schema([
                        Forms\Components\FileUpload::make('site_logo')
                            ->label('Site Logo')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                            ->disk('public')
                            ->directory('logo')
                            ->imageResizeMode('contain')
                            ->imageResizeTargetHeight('80')
                            ->helperText('Header და Footer-ში გამოჩნდება. სიმაღლე: 40px')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('footer_tagline')
                            ->label('Footer Tagline')->rows(2)->columnSpanFull(),
                    ]),

                Section::make('Mail')
                    ->description('Configure the mailbox identity used for sending and receiving email.')
                    ->schema([
                        Forms\Components\Toggle::make('mail_enabled')
                            ->label('Mail Enabled')
                            ->default(true)
                            ->onColor('success'),

                        Forms\Components\TextInput::make('mail_sender_name')
                            ->label('Sender Name')
                            ->placeholder(config('mail.from.name') ?: config('app.name'))
                            ->maxLength(255),

                        Forms\Components\TextInput::make('mail_sender_email')
                            ->label('Sender Email')
                            ->placeholder(config('mail.from.address'))
                            ->email()
                            ->maxLength(255)
                            ->helperText('This address must belong to a verified sending domain.'),

                        Forms\Components\TextInput::make('mail_inbox_email')
                            ->label('Inbox Email')
                            ->placeholder(config('agency.admin_email'))
                            ->email()
                            ->maxLength(255)
                            ->helperText('Incoming messages are expected to be delivered to this mailbox.'),

                        Forms\Components\TextInput::make('mail_reply_to')
                            ->label('Reply-To')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Optional. Leave empty to use the sender email.'),

                        Forms\Components\TextInput::make('mail_admin_notification_email')
                            ->label('Admin Notification Email')
                            ->placeholder(config('agency.admin_email'))
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Contact & Location')
                    ->schema([
                        Forms\Components\TextInput::make('site_email')
                            ->label('Public Email')->email()->maxLength(255),
                        Forms\Components\TextInput::make('site_phone')
                            ->label('Phone')->maxLength(255),
                        Forms\Components\Textarea::make('contact_address')
                            ->label('Address')->rows(2),
                        Forms\Components\TextInput::make('working_hours')
                            ->label('Working Hours')
                            ->placeholder('Mon-Fri: 9:00-18:00'),
                        Forms\Components\Textarea::make('google_maps_embed')
                            ->label('Google Maps Embed Code')
                            ->rows(3)->columnSpanFull(),
                    ])->columns(2),

                Section::make('SEO & Analytics')
                    ->schema([
                        Forms\Components\TextInput::make('seo_default_title')
                            ->label('Default SEO Title')
                            ->placeholder(config('agency.seo.title_suffix'))
                            ->maxLength(255),
                        Forms\Components\Textarea::make('seo_default_description')
                            ->label('Default SEO Description')
                            ->rows(2)
                            ->maxLength(500),
                        Forms\Components\Textarea::make('google_analytics')
                            ->label('Google Analytics Code')
                            ->placeholder('<!-- Google tag (gtag.js) ... -->')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('Google Analytics-ის სრული script კოდი'),
                        Forms\Components\Textarea::make('head_scripts')
                            ->label('Head Scripts / Verification Codes')
                            ->placeholder('<!-- Google Search Console, Bing, etc. verification meta tags or scripts -->')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('Google Search Console, Bing Webmaster, Facebook Domain Verification და სხვა კოდები'),
                    ])->columns(2)->collapsed(),

                Section::make('Modules')
                    ->description('Enable/disable sections and customize their display names')
                    ->schema([
                        Forms\Components\Toggle::make('module_portfolio')
                            ->label('Portfolio')
                            ->default(true)
                            ->onColor('success'),
                        Forms\Components\TextInput::make('module_portfolio_label')
                            ->label('Portfolio Menu Label')
                            ->placeholder('Portfolio')
                            ->maxLength(50),
                        Forms\Components\Toggle::make('module_services')
                            ->label('Services')
                            ->default(true)
                            ->onColor('success'),
                        Forms\Components\TextInput::make('module_services_label')
                            ->label('Services Menu Label')
                            ->placeholder('Services')
                            ->maxLength(50),
                        Forms\Components\Toggle::make('module_blog')
                            ->label('Blog')
                            ->default(true)
                            ->onColor('success'),
                        Forms\Components\TextInput::make('module_blog_label')
                            ->label('Blog Menu Label')
                            ->placeholder('Blog')
                            ->maxLength(50),
                        Forms\Components\Toggle::make('module_guides')
                            ->label('Guides')
                            ->default(true)
                            ->onColor('success'),
                        Forms\Components\TextInput::make('module_guides_label')
                            ->label('Guides Menu Label')
                            ->placeholder('Guides')
                            ->maxLength(50),
                        Forms\Components\Toggle::make('module_testimonials')
                            ->label('Testimonials')
                            ->default(true)
                            ->onColor('success'),
                        Forms\Components\TextInput::make('module_testimonials_label')
                            ->label('Testimonials Menu Label')
                            ->placeholder('Testimonials')
                            ->maxLength(50),
                        Forms\Components\Toggle::make('module_shop')
                            ->label('Shop')
                            ->default(true)
                            ->onColor('success'),
                        Forms\Components\TextInput::make('module_shop_label')
                            ->label('Shop Menu Label')
                            ->placeholder('Shop')
                            ->maxLength(50),
                    ])->columns(2)->collapsed(),

                Section::make('Social Media')
                    ->schema([
                        Forms\Components\TextInput::make('social_facebook')
                            ->label('Facebook')->url()->maxLength(255),
                        Forms\Components\TextInput::make('social_twitter')
                            ->label('X (Twitter)')->url()->maxLength(255),
                        Forms\Components\TextInput::make('social_instagram')
                            ->label('Instagram')->url()->maxLength(255),
                        Forms\Components\TextInput::make('social_linkedin')
                            ->label('LinkedIn')->url()->maxLength(255),
                    ])->columns(2),

                Section::make('Legal')
                    ->schema([
                        Forms\Components\TextInput::make('copyright_text')
                            ->label('Copyright Text')
                            ->placeholder('© {year} ' . config('agency.name') . '. All rights reserved.')
                            ->maxLength(255)
                            ->helperText('Use {year} to insert the current year.'),
                    ])->columns(1)->collapsed(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $oldSettings = SiteSetting::pluck('value', 'key');

        foreach ($data as $key => $value) {
            $newValue = $value ?? '';

            SiteSetting::updateOrCreate(['key' => $key], ['value' => $newValue]);
            AdminAudit::logSettingChange($key, $oldSettings->get($key), $newValue);
        }

        // Module toggles — MenuItem-ები განახლდეს
        $moduleMap = [
            'module_services'   => '/services',
            'module_portfolio'  => '/portfolio',
            'module_blog'       => '/blog',
            'module_guides'     => '/guides',
            'module_shop'       => '/shop',
        ];

        foreach ($moduleMap as $settingKey => $url) {
            $isActive = !empty($data[$settingKey]);
            \App\Models\MenuItem::where('url', $url)->update(['is_active' => $isActive]);

            // Label განახლება
            $labelKey = $settingKey . '_label';
            if (!empty($data[$labelKey])) {
                \App\Models\MenuItem::where('url', $url)->update(['label' => $data[$labelKey]]);
            }
        }

        // Menu cache გავასუფთავოთ
        Cache::forget('site.settings');
        Cache::forget('menu.items');

        app(MailSettings::class)->clearCache();

        Notification::make()->title('Settings saved!')->success()->send();
    }
}
