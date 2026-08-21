<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm user_id cho nhật ký audit (ai đã gửi lệnh cài đặt).
     */
    public function up(): void
    {
        Schema::table('device_commands', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('device_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('device_commands', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
