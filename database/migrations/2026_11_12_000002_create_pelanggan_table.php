<?php

use App\Enums\JenisPelanggan;
use App\Enums\StatusPelanggan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelanggan', function (Blueprint $table) {
            $table->id();
            $table->char('idpel', 12)->unique();
            $table->foreignId('rute_id')->constrained('rute')->restrictOnDelete();
            $table->unsignedInteger('urutan_rute');
            $table->string('nama', 100);
            $table->string('alamat');
            $table->string('kelurahan', 100);
            $table->enum('jenis', JenisPelanggan::values());
            $table->string('golongan_tarif', 10);
            $table->unsignedInteger('daya_va');
            $table->string('nomor_meter', 20)->unique();
            $table->string('letak_meter')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('status', StatusPelanggan::values())->default(StatusPelanggan::Aktif->value);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rute_id', 'urutan_rute']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelanggan');
    }
};
