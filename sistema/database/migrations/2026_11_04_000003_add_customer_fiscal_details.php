<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('kind', 16)->nullable()->default('mostrador')->index();
            foreach (['company', 'position', 'legal_name', 'tax_regime', 'cfdi_use', 'tax_zip', 'street', 'ext_number', 'int_number', 'neighborhood', 'city', 'state', 'zip', 'address_notes'] as $column) {
                $table->string($column)->nullable();
            }
            $table->index('rfc');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropIndex(['rfc']);
            $table->dropColumn(['kind', 'company', 'position', 'legal_name', 'tax_regime', 'cfdi_use', 'tax_zip', 'street', 'ext_number', 'int_number', 'neighborhood', 'city', 'state', 'zip', 'address_notes']);
        });
    }
};
