<?php

use App\Enums\HasilKunjungan;
use App\Enums\PenyebabKoreksi;
use App\Enums\StatusCatatan;
use App\Enums\StatusInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 30)->unique();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periode')->restrictOnDelete();
            $table->foreignId('pencatatan_meter_id')->unique()->constrained('pencatatan_meter')->restrictOnDelete();
            $table->foreignId('tarif_id')->constrained('tarif')->restrictOnDelete();
            $table->decimal('pemakaian_kwh', 12, 1);
            $table->decimal('harga_per_kwh', 10, 2);
            $table->decimal('biaya_beban', 12, 2);
            $table->decimal('pajak', 12, 2);
            $table->decimal('total', 14, 2);
            $table->date('jatuh_tempo');
            $table->enum('status', StatusInvoice::values())->default(StatusInvoice::BelumBayar->value);
            $table->timestamp('dibayar_at')->nullable();
            $table->timestamps();

            $table->unique(['pelanggan_id', 'periode_id']);
            $table->index(['status', 'jatuh_tempo']);
        });

        Schema::create('kunjungan_penagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoice')->restrictOnDelete();
            $table->foreignId('biller_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('dikunjungi_at');
            $table->enum('hasil', HasilKunjungan::values());
            $table->date('tanggal_janji')->nullable();
            $table->decimal('nominal_dibayar', 14, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('jarak_m', 10, 1)->nullable();
            $table->enum('status', StatusCatatan::values())->default(StatusCatatan::Draft->value);
            $table->unsignedTinyInteger('jumlah_revisi')->default(0);
            $table->timestamps();

            $table->index(['biller_id', 'status']);
        });

        Schema::create('koreksi_rekening', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periode')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoice')->restrictOnDelete();
            $table->foreignId('pencatatan_meter_id')->nullable()->constrained('pencatatan_meter')->restrictOnDelete();
            $table->foreignId('diajukan_oleh')->constrained('users')->restrictOnDelete();
            $table->decimal('stand_lama', 12, 1);
            $table->decimal('stand_baru', 12, 1);
            $table->decimal('kwh_lama', 12, 1);
            $table->decimal('kwh_baru', 12, 1);
            $table->decimal('tagihan_lama', 14, 2);
            $table->decimal('tagihan_baru', 14, 2);
            $table->enum('penyebab', PenyebabKoreksi::values());
            $table->text('alasan');
            $table->enum('status', StatusCatatan::values())->default(StatusCatatan::Draft->value);
            $table->unsignedTinyInteger('jumlah_revisi')->default(0);
            $table->timestamps();

            $table->index(['status', 'periode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('koreksi_rekening');
        Schema::dropIfExists('kunjungan_penagihan');
        Schema::dropIfExists('invoice');
    }
};
