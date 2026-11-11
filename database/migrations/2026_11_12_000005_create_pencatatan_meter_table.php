<?php

use App\Enums\JenisPelanggan;
use App\Enums\KondisiMeter;
use App\Enums\StatusCatatan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pencatatan_meter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('periode')->restrictOnDelete();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->restrictOnDelete();
            $table->foreignId('biller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('penugasan_id')->constrained('penugasan')->restrictOnDelete();
            $table->enum('jenis', JenisPelanggan::values());
            $table->decimal('stand_lalu', 12, 1)->nullable();
            $table->decimal('stand_kini', 12, 1)->nullable();
            $table->decimal('pemakaian_kwh', 12, 1)->nullable();
            $table->decimal('sisa_kwh', 12, 2)->nullable();
            $table->enum('kondisi_meter', KondisiMeter::values())->default(KondisiMeter::Normal->value);
            $table->text('keterangan')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('jarak_m', 10, 1)->nullable();
            $table->timestamp('dikirim_at')->nullable();
            $table->boolean('tepat_waktu')->nullable();
            $table->enum('status', StatusCatatan::values())->default(StatusCatatan::Draft->value);
            $table->unsignedTinyInteger('jumlah_revisi')->default(0);
            $table->timestamp('disetujui_at')->nullable();
            $table->timestamps();

            $table->unique(['periode_id', 'pelanggan_id']);
            $table->index(['biller_id', 'periode_id', 'status']);
            $table->index(['status', 'dikirim_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pencatatan_meter');
    }
};
