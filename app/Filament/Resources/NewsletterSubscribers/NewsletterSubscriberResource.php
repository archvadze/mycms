<?php
namespace App\Filament\Resources\NewsletterSubscribers;

use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Mail\NewsletterBroadcastMail;
use App\Models\NewsletterSubscriber;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Collection;
use App\Support\AdminAccess;

class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;
    protected static ?string $navigationLabel = 'Newsletter';
    protected static ?int $navigationSort = 7;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageContent();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Operations';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-envelope-open';
    }

    public static function getNavigationBadge(): ?string
    {
        return NewsletterSubscriber::where('status', 'active')->count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->default('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'active'       => 'success',
                        'unsubscribed' => 'danger',
                        default        => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Subscribed')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active'       => 'Active',
                        'unsubscribed' => 'Unsubscribed',
                    ]),
            ])
            ->actions([])
            ->toolbarActions([
                Action::make('broadcast')
                    ->label('Send Newsletter')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->form([
                        TextInput::make('emailSubject')
                            ->label('Subject')
                            ->required(),
                        Textarea::make('emailBody')
                            ->label('Message')
                            ->rows(8)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $subscribers = NewsletterSubscriber::where('status', 'active')->get();
                        foreach ($subscribers as $subscriber) {
                            Mail::to($subscriber->email)
                                ->send(new NewsletterBroadcastMail(
                                    emailSubject: $data['emailSubject'],
                                    emailBody: $data['emailBody'],
                                    subscriber: $subscriber,
                                ));
                        }
                        \Filament\Notifications\Notification::make()
                            ->title('Newsletter sent to ' . $subscribers->count() . ' subscribers')
                            ->success()
                            ->send();
                    }),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListNewsletterSubscribers::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
