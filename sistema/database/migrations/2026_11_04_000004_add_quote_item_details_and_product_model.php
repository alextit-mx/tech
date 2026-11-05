<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->string('model')->nullable());
        Schema::table('quote_items', function (Blueprint $table) {
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('warranty')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', fn (Blueprint $table) => $table->dropColumn(['brand', 'model', 'warranty']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('model'));
    }
};
