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
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workflow_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('version_number');

            $table->string('status', 20);

            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->unique(['workflow_id', 'version_number']);
        });

        DB::statement("
            CREATE UNIQUE INDEX workflow_versions_one_draft_per_workflow
            ON workflow_versions (workflow_id)
            WHERE status = 'draft'
        ");

        DB::statement("
            CREATE UNIQUE INDEX workflow_versions_one_published_per_workflow
            ON workflow_versions (workflow_id)
            WHERE status = 'published'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
    }
};
