<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('audit-log.connection');
        
        Schema::connection($connection)->create('audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Primary Entity Reference
            $table->string('entity_type', 255);
            $table->unsignedBigInteger('entity_id');
            
            // Action Type (enum as integer - more efficient)
            $table->unsignedTinyInteger('action');
            
            // Actor Information
            $table->string('actor_type', 50);
            $table->unsignedBigInteger('actor_id')->nullable();
            
            // Change Data (JSON)
            $table->json('attributes')->nullable();
            $table->json('relations')->nullable();
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index(['entity_type', 'entity_id'], 'idx_entity');
            $table->index(['actor_type', 'actor_id'], 'idx_actor');
            $table->index('action', 'idx_action');
            $table->index('created_at', 'idx_created_at');
            $table->index(['entity_type', 'entity_id', 'action'], 'idx_entity_action');
        });
    }

    public function down(): void
    {
        $connection = config('audit-log.connection');
        Schema::connection($connection)->dropIfExists('audit_logs');
    }
};
