<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 10, 2);
            $table->decimal('max_amount', 10, 2)->nullable();
            $table->integer('max_usage_per_user')->default(1);
            $table->integer('max_total_usage')->nullable();
            $table->integer('current_usage')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('stacking_order')->default(0);
            $table->json('conditions')->nullable(); // For future extensibility
            $table->timestamps();
            
            $table->index(['is_active', 'expires_at']);
            $table->index(['type', 'stacking_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};

