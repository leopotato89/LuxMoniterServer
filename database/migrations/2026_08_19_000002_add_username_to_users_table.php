<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('email');
        });

        // Backfill: gán username cho các user đã có (từ phần đầu email, đảm bảo unique)
        $taken = [];
        $users = DB::table('users')->whereNull('username')->orderBy('id')->get();

        foreach ($users as $user) {
            $base = Str::lower(Str::before($user->email, '@'));
            $username = $base;
            $i = 1;
            while (in_array($username, $taken, true)) {
                $username = $base.$i;
                $i++;
            }
            $taken[] = $username;

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
