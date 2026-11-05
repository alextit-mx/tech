<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // stock = entrega inmediata (columna CENTRO de la lista); stock_24h = llega al día siguiente (columna 24H).
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('stock_24h')->default(0)->after('stock');
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->string('image_mobile')->nullable();
            $table->string('alt');
            $table->string('link')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('stock_24h'));
    }
};
