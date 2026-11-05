<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Equipos armados que la tienda sugiere (se personalizan en /arma-tu-pc).
        Schema::create('builds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->string('use', 20)->default('gaming1440');
            $table->string('image')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('build_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('build_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('slot', 20);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unique(['build_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('build_product');
        Schema::dropIfExists('builds');
    }
};
