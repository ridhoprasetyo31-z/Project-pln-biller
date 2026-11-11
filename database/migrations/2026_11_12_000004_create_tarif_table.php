<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarif', function (Blueprint $table) {
            $table->id();
            $table->string('golongan', 10);
            $table->unsignedInteger('daya_min_va');
            $table->unsignedInteger('daya_max_va');
            $table->decimal('harga_per_kwh', 10, 2);
            $table->decimal('biaya_beban', 12, 2)->default(0);
            $table->date('berlaku_mulai');
            $table->date('berlaku_sampai')->nullable();
            $table->timestamps();

            $table->index(['golongan', 'daya_min_va', 'daya_max_va']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif');
    }
};
