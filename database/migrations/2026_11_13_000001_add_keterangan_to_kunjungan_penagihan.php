<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan biller saat kunjungan penagihan (alasan menolak, pesan janji bayar, dll).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kunjungan_penagihan', function (Blueprint $table) {
            $table->text('keterangan')->nullable()->after('nominal_dibayar');
        });
    }

    public function down(): void
    {
        Schema::table('kunjungan_penagihan', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });
    }
};
