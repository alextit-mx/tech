<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_type');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial')->nullable();
            $table->text('accessories')->nullable();
            $table->text('reported_issue');
            $table->text('diagnosis')->nullable();
            $table->text('work_done')->nullable();
            $table->decimal('estimate', 12, 2)->nullable();
            $table->decimal('final_cost', 12, 2)->nullable();
            $table->string('status')->default('received');
            $table->dateTime('received_at');
            $table->date('promised_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('service_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('starts_at');
            $table->text('terms')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('warranty_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('serial')->nullable();
            $table->unsignedInteger('months');
        });

        Schema::create('service_plans', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('visits_included');
            $table->decimal('price', 12, 2)->nullable();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('service_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('visited_at');
            $table->string('device')->nullable();
            $table->text('work');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_visits');
        Schema::dropIfExists('service_plans');
        Schema::dropIfExists('warranty_items');
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('service_order_events');
        Schema::dropIfExists('service_orders');
    }
};
