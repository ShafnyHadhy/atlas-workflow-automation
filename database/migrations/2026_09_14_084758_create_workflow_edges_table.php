<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_edges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workflow_version_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->uuid('source_node_key');
            $table->uuid('target_node_key');

            $table->jsonb('condition')->nullable();

            $table->timestamps();

            $table->unique([
                'workflow_version_id',
                'source_node_key',
                'target_node_key',
            ]);
        });

        DB::statement('
            ALTER TABLE workflow_edges
            ADD CONSTRAINT workflow_edges_source_node_foreign
            FOREIGN KEY (workflow_version_id, source_node_key)
            REFERENCES workflow_nodes (workflow_version_id, node_key)
            ON DELETE CASCADE
        ');

        DB::statement('
            ALTER TABLE workflow_edges
            ADD CONSTRAINT workflow_edges_target_node_foreign
            FOREIGN KEY (workflow_version_id, target_node_key)
            REFERENCES workflow_nodes (workflow_version_id, node_key)
            ON DELETE CASCADE
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_edges');
    }
};
