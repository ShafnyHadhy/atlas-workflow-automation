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
        Schema::create('workflow_nodes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workflow_version_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->uuid('node_key');

            $table->string('type', 30);

            $table->jsonb('configuration');

            $table->jsonb('position');

            $table->timestamps();

            $table->unique(['workflow_version_id', 'node_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_nodes');
    }
};
