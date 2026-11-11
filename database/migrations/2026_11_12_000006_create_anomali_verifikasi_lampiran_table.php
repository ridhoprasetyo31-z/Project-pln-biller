<?php

use App\Enums\AlasanTolak;
use App\Enums\JenisAnomali;
use App\Enums\JenisLampiran;
use App\Enums\KeputusanVerifikasi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anomali', function (Blueprint $table) {
            $table->id();
            $table->morphs('anomaliable');
            $table->enum('jenis', JenisAnomali::values());
            $table->decimal('nilai', 14, 2)->nullable();
            $table->string('keterangan');
            $table->timestamps();
        });

        // Append-only: tiap keputusan verifikasi menjadi baris baru.
        Schema::create('verifikasi', function (Blueprint $table) {
            $table->id();
            $table->morphs('verifiable');
            $table->foreignId('verifikator_id')->constrained('users')->restrictOnDelete();
            $table->enum('keputusan', KeputusanVerifikasi::values());
            $table->enum('alasan', AlasanTolak::values())->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('lampiran', function (Blueprint $table) {
            $table->id();
            $table->morphs('lampiranable');
            $table->enum('jenis', JenisLampiran::values());
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('ukuran_kb');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('diambil_at')->nullable();
            $table->foreignId('diunggah_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lampiran');
        Schema::dropIfExists('verifikasi');
        Schema::dropIfExists('anomali');
    }
};
