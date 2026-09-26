<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    /**
     * Chỉ admin được tạo thiết bị (quyết định nằm ở DevicePolicy).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Device::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'serial' => ['required', 'string', 'max:50', 'unique:devices,serial'],
            'name' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'serial.unique' => 'Serial này đã tồn tại trong hệ thống.',
            'owner_id.exists' => 'Không tìm thấy người dùng để gán.',
        ];
    }
}
