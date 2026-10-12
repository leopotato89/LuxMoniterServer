<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_alert_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('battery_alert_mode', 16)->default('soc');
            $table->unsignedTinyInteger('low_battery_soc')->default(20);
            $table->decimal('low_battery_voltage', 4, 1)->default(48.0);
            $table->boolean('notify_fault')->default(true);
            $table->boolean('notify_grid')->default(true);
            $table->boolean('notify_offline')->default(true);
            $table->boolean('notify_recovery')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_alert_settings');
    }
};
