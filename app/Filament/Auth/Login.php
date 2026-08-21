<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

class Login extends BaseLogin
{
    /**
     * Field nhận cả email lẫn username (state key = "login").
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('Email hoặc tên đăng nhập')
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    /**
     * Chuyển đầu vào thành credentials: tìm user theo email HOẶC username,
     * rồi trả về email thật để guard xác thực mật khẩu.
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $login = trim((string) ($data['login'] ?? ''));

        $user = User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        return [
            'email' => $user?->email ?? 'invalid-user@localhost',
            'password' => $data['password'],
        ];
    }
}
