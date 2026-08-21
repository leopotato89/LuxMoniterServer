<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();

            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Register / bitfield đã ghi
            $table->unsignedSmallInteger('reg');
            $table->unsignedTinyInteger('bit')->nullable();
            $table->unsignedInteger('value');

            $table->text('request_json')->nullable();

            // sent | success | failed | timeout
            $table->string('status')->default('sent');
            $table->boolean('ok')->nullable();
            $table->text('result_json')->nullable();
            $table->string('error')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_commands');
    }
};
