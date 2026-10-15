<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\MqttService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

class FirmwareController extends Controller
{
    /**
     * Trả về manifest cấu hình các bản firmware cho Web Flasher và Mobile App.
     */
    public function manifest(Request $request): JsonResponse
    {
        $manifestPath = public_path('firmware/manifest.json');

        if (! File::exists($manifestPath)) {
            return response()->json([
                'error' => 'Chưa cấu hình firmware manifest trên máy chủ.',
            ], 404);
        }

        $manifest = json_decode((string) File::get($manifestPath), true);
        if (! is_array($manifest)) {
            return response()->json([
                'error' => 'File firmware manifest không hợp lệ.',
            ], 500);
        }

        $baseUrl = $request->getSchemeAndHttpHost();

        // Chuẩn hóa và gán URL tuyệt đối cho từng file binary
        if (isset($manifest['boards']) && is_array($manifest['boards'])) {
            foreach ($manifest['boards'] as $boardKey => &$board) {
                if (isset($board['ota_file'])) {
                    $board['ota_url'] = "{$baseUrl}/firmware/{$boardKey}/{$board['ota_file']}";
                    $otaFilePath = public_path("firmware/{$boardKey}/{$board['ota_file']}");
                    if (File::exists($otaFilePath)) {
                        $board['ota_md5'] = md5_file($otaFilePath);
                        $board['ota_size'] = File::size($otaFilePath);
                    }
                }

                if (isset($board['parts']) && is_array($board['parts'])) {
                    foreach ($board['parts'] as &$part) {
                        $fileName = $part['file'] ?? '';
                        $part['url'] = "{$baseUrl}/firmware/{$boardKey}/{$fileName}";
                        $partFilePath = public_path("firmware/{$boardKey}/{$fileName}");
                        if (File::exists($partFilePath)) {
                            $part['size'] = File::size($partFilePath);
                            $part['md5'] = md5_file($partFilePath);
                        }
                    }
                }
            }
        }

        return response()->json($manifest);
    }

    /**
     * Kích hoạt nâng cấp OTA từ xa cho thiết bị qua MQTT.
     */
    public function triggerOta(Request $request, Device $device, MqttService $mqttService): JsonResponse
    {
        Gate::authorize('update', $device);

        $validated = $request->validate([
            'board' => ['nullable', 'string', 'in:devkit,c3'],
        ]);

        $boardType = $validated['board'] ?? 'devkit';
        $manifestPath = public_path('firmware/manifest.json');

        if (! File::exists($manifestPath)) {
            return response()->json([
                'error' => 'Firmware manifest chưa tồn tại trên hệ thống.',
            ], 404);
        }

        $manifest = json_decode((string) File::get($manifestPath), true);
        $boardConfig = $manifest['boards'][$boardType] ?? null;

        if (! $boardConfig || empty($boardConfig['ota_file'])) {
            return response()->json([
                'error' => "Không tìm thấy cấu hình firmware cho dòng bo mạch {$boardType}.",
            ], 404);
        }

        $fileName = $boardConfig['ota_file'];
        $filePath = public_path("firmware/{$boardType}/{$fileName}");

        if (! File::exists($filePath)) {
            return response()->json([
                'error' => "File firmware {$fileName} chưa được tải lên máy chủ.",
            ], 404);
        }

        $baseUrl = $request->getSchemeAndHttpHost();
        $firmwareUrl = "{$baseUrl}/firmware/{$boardType}/{$fileName}";
        $firmwareMd5 = md5_file($filePath) ?: '';
        $version = $manifest['version'] ?? 'latest';

        $published = $mqttService->publishOta($device->serial, $firmwareUrl, $firmwareMd5, (string) $version);

        if (! $published) {
            return response()->json([
                'error' => 'Gửi lệnh OTA qua MQTT thất bại. Vui lòng kiểm tra lại kết nối broker.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Đã gửi lệnh kích hoạt nâng cấp OTA firmware ({$version}) tới biến tần {$device->serial}.",
            'version' => $version,
            'url' => $firmwareUrl,
            'md5' => $firmwareMd5,
        ]);
    }
}
