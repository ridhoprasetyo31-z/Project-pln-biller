<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rute', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('kode', 20);
            $table->string('nama', 100);
            $table->string('kelurahan', 100);
            $table->foreignId('biller_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['unit_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rute');
    }
};
