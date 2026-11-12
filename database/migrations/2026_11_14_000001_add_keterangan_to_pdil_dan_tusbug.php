<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan biller untuk koordinator pada PDIL dan pelaksanaan TUSBUG.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdil', function (Blueprint $table) {
            $table->text('keterangan')->nullable()->after('data_lama');
        });
        Schema::table('tusbug', function (Blueprint $table) {
            $table->text('keterangan')->nullable()->after('hasil');
        });
    }

    public function down(): void
    {
        Schema::table('pdil', fn (Blueprint $table) => $table->dropColumn('keterangan'));
        Schema::table('tusbug', fn (Blueprint $table) => $table->dropColumn('keterangan'));
    }
};
