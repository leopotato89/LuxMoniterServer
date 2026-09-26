<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceRequest extends FormRequest
{
    /**
     * Admin hoặc chủ sở hữu được sửa (quyết định nằm ở DevicePolicy).
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('device'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            // Chỉ admin được đổi chủ sở hữu; user thường gửi lên sẽ bị 422 thay vì
            // âm thầm bỏ qua (tránh chiếm thiết bị của người khác).
            'owner_id' => $this->user()->isAdmin()
                ? ['sometimes', 'nullable', 'integer', 'exists:users,id']
                : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'owner_id.prohibited' => 'Bạn không được phép đổi chủ sở hữu thiết bị.',
            'owner_id.exists' => 'Không tìm thấy người dùng để gán.',
        ];
    }
}
