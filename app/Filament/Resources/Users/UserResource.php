<?php
namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationLabel = 'Users';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageUsers();
    }

    public static function canCreate(): bool
    {
        return AdminAccess::canManageUsers();
    }

    public static function canView(Model $record): bool
    {
        return AdminAccess::canManageUsers();
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canManageUsers();
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof User && AdminAccess::canDeleteUser($record);
    }

    public static function canDeleteAny(): bool
    {
        return AdminAccess::canManageUsers();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-users';
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit'   => EditUser::route('/{record}/edit'),
        ];
    }
}
