<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Xác thực bằng Sanctum personal access token (Bearer) — dùng chung cho SPA và mobile.
 * Cố ý KHÔNG dùng Auth::attempt() để tránh tạo session trong request API.
 */
class AuthController
{
    /**
     * Đăng nhập bằng email HOẶC username.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->findByLogin($request->string('login')->toString());

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'login' => 'Thông tin đăng nhập không đúng.',
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Tài khoản đã bị đình chỉ.'], 403);
        }

        return $this->tokenResponse($user, $this->deviceName($request));
    }

    /**
     * Đăng ký tài khoản mới rồi trả token luôn.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'username' => $request->string('username')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'is_active' => true,
        ]);

        return $this->tokenResponse($user, $this->deviceName($request));
    }

    /**
     * Thông tin user đang đăng nhập (dùng để khôi phục phiên khi tải lại SPA).
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    /**
     * Thu hồi token hiện tại (chỉ thiết bị đang gọi).
     *
     * API này chỉ xác thực bằng token (không bật Sanctum stateful), nên
     * currentAccessToken() luôn là PersonalAccessToken — tức có delete().
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Quên mật khẩu.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
                    ? response()->json(['message' => __($status)])
                    : response()->json(['message' => __($status)], 400);
    }

    /**
     * Đặt lại mật khẩu.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
                    ? response()->json(['message' => __($status)])
                    : response()->json(['message' => __($status)], 400);
    }

    /**
     * Đổi mật khẩu.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        return response()->json(['message' => 'Đổi mật khẩu thành công.']);
    }

    /**
     * Tìm user theo email hoặc username.
     */
    private function findByLogin(string $login): ?User
    {
        return User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();
    }

    /**
     * Tên thiết bị để phân biệt các token đã phát hành (mobile gửi lên khi đăng nhập).
     */
    private function deviceName(Request $request): string
    {
        return $request->string('device_name')->toString() ?: 'web';
    }

    private function tokenResponse(User $user, string $deviceName): JsonResponse
    {
        return response()->json([
            'data' => [
                'token' => $user->createToken($deviceName)->plainTextToken,
                'user' => new UserResource($user),
            ],
        ]);
    }
}
