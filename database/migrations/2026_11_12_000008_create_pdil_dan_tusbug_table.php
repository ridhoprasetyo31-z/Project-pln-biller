<?php

use App\Enums\HasilTusbug;
use App\Enums\JenisPdil;
use App\Enums\JenisPelanggan;
use App\Enums\StatusCatatan;
use App\Enums\StatusTusbug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdil', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis', JenisPdil::values());
            $table->foreignId('pelanggan_id')->nullable()->constrained('pelanggan')->restrictOnDelete();
            $table->foreignId('biller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('rute_id')->constrained('rute')->restrictOnDelete();
            // Data pelanggan yang diusulkan.
            $table->char('idpel', 12)->nullable();
            $table->string('nama', 100);
            $table->string('alamat');
            $table->string('kelurahan', 100);
            $table->enum('jenis_pelanggan', JenisPelanggan::values());
            $table->string('golongan_tarif', 10);
            $table->unsignedInteger('daya_va');
            $table->string('nomor_meter', 20)->nullable();
            $table->string('letak_meter')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('data_lama')->nullable();
            $table->enum('status', StatusCatatan::values())->default(StatusCatatan::Draft->value);
            $table->unsignedTinyInteger('jumlah_revisi')->default(0);
            $table->timestamps();

            $table->index(['biller_id', 'status']);
        });

        Schema::create('tusbug', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_surat', 50)->unique();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->restrictOnDelete();
            $table->foreignId('biller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('ditugaskan_oleh')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('jumlah_lembar');
            $table->decimal('total_tunggakan', 14, 2);
            $table->date('tanggal_rencana');
            $table->timestamp('dilaksanakan_at')->nullable();
            $table->enum('hasil', HasilTusbug::values())->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('jarak_m', 10, 1)->nullable();
            $table->enum('status', StatusTusbug::values())->default(StatusTusbug::Ditugaskan->value);
            $table->unsignedTinyInteger('jumlah_revisi')->default(0);
            $table->timestamps();

            $table->index(['biller_id', 'status']);
        });

        Schema::create('tusbug_invoice', function (Blueprint $table) {
            $table->foreignId('tusbug_id')->constrained('tusbug')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoice')->cascadeOnDelete();
            $table->primary(['tusbug_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tusbug_invoice');
        Schema::dropIfExists('tusbug');
        Schema::dropIfExists('pdil');
    }
};
