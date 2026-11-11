<?php

use App\Enums\StatusPeriode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode', function (Blueprint $table) {
            $table->id();
            $table->char('kode', 6)->unique(); // YYYYMM
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->enum('status', StatusPeriode::values())->default(StatusPeriode::Draft->value);
            $table->timestamp('ditutup_at')->nullable();
            $table->foreignId('ditutup_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('penugasan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('periode')->restrictOnDelete();
            $table->foreignId('rute_id')->constrained('rute')->restrictOnDelete();
            $table->foreignId('biller_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('target_pelanggan');
            $table->timestamps();

            $table->unique(['periode_id', 'rute_id']);
            $table->index(['biller_id', 'periode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan');
        Schema::dropIfExists('periode');
    }
};
