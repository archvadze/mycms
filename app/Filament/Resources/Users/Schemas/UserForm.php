<?php
namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Support\AdminAccess;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('status')
                            ->options([
                                'active'  => 'Active',
                                'blocked' => 'Blocked',
                                'pending' => 'Pending',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false),
                        Select::make('roles')
                            ->relationship(
                                'roles',
                                'name',
                                modifyQueryUsing: fn(Builder $query): Builder =>
                                $query->whereIn('name', AdminAccess::assignableRoles())
                            )
                            ->multiple()
                            ->preload()
                            ->native(false)
                            ->disabled(
                                fn(?User $record): bool =>
                                $record instanceof User
                                && AdminAccess::isCurrentUser($record)
                                && $record->hasRole(AdminAccess::SUPER_ADMIN)
                            ),
                    ]),
                Section::make('Email Verification')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('email_verified_at')
                            ->label('Verified At')
                            ->helperText('Set manually to verify or clear to unverify.')
                            ->nullable(),
                        Actions::make([
                            Action::make('verify_now')
                                ->label('Verify Now')
                                ->icon('heroicon-o-check-badge')
                                ->color('success')
                                ->action(function ($set) {
                                    $set('email_verified_at', now()->format('Y-m-d H:i:s'));
                                }),
                            Action::make('unverify')
                                ->label('Remove Verification')
                                ->icon('heroicon-o-x-circle')
                                ->color('danger')
                                ->action(function ($set) {
                                    $set('email_verified_at', null);
                                }),
                        ]),
                    ]),
                Section::make('Password')
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->helperText('Leave blank to keep current password.')
                            ->maxLength(255),
                    ]),
            ]);
    }
}
