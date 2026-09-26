<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Xác minh thiết bị bằng serial + mã lấy từ trang web của ESP32.
 * Ai đã đăng nhập cũng được thử, nên không có policy ở đây.
 */
class ClaimDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'serial' => ['required', 'string', 'max:50'],
            'device_code' => ['required', 'string', 'max:16'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'serial.required' => 'Vui lòng nhập serial thiết bị.',
            'device_code.required' => 'Vui lòng nhập mã thiết bị lấy từ ESP32.',
        ];
    }
}
