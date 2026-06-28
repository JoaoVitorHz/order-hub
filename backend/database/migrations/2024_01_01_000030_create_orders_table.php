<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->unsignedBigInteger('affiliate_id');
            $table->string('status')->default('pending');
            $table->decimal('total_value', 12, 2)->default(0);
            $table->date('ordered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('affiliate_id')
                ->references('id')->on('affiliates')
                ->cascadeOnDelete();

            // Índice composto otimizado para os filtros mais comuns da API
            $table->index(['affiliate_id', 'status', 'created_at'], 'idx_orders_affiliate_status_date');
            $table->index(['status', 'created_at'], 'idx_orders_status_date');
            $table->index(['total_value'], 'idx_orders_total_value');
            $table->index(['created_at'], 'idx_orders_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
