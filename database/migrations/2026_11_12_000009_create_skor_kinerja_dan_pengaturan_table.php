<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skor_kinerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('periode')->restrictOnDelete();
            $table->foreignId('biller_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('target')->default(0);
            $table->unsignedInteger('tercatat')->default(0);
            $table->unsignedInteger('terverifikasi')->default(0);
            $table->unsignedInteger('disetujui_tanpa_revisi')->default(0);
            $table->unsignedInteger('jumlah_tepat_waktu')->default(0);
            $table->unsignedInteger('jumlah_bukti_lengkap')->default(0);
            $table->decimal('akurasi', 5, 2)->default(0);
            $table->decimal('penyelesaian', 5, 2)->default(0);
            $table->decimal('ketepatan_waktu', 5, 2)->default(0);
            $table->decimal('bukti_lengkap', 5, 2)->default(0);
            $table->decimal('skor', 5, 2)->default(0);
            $table->unsignedSmallInteger('peringkat')->nullable();
            $table->boolean('is_final')->default(false);
            $table->timestamp('dihitung_at')->nullable();
            $table->timestamps();

            $table->unique(['periode_id', 'biller_id']);
            $table->index(['periode_id', 'peringkat']);
        });

        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 50)->unique();
            $table->json('nilai');
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
        Schema::dropIfExists('skor_kinerja');
    }
};
