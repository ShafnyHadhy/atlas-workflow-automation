<?php

namespace App\Actions\Workflows;

use App\Enums\WorkflowVersionStatus;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishWorkflowVersion
{
    public function __construct(
        private readonly ValidateWorkflowGraph $validateWorkflowGraph,
    ) {}

    public function execute(WorkflowVersion $version): WorkflowVersion
    {
        return DB::transaction(function () use ($version) {
            $workflow = Workflow::query()
                ->whereKey($version->workflow_id)
                ->lockForUpdate()
                ->firstOrFail();

            $draft = $workflow->versions()
                ->whereKey($version->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($draft->status !== WorkflowVersionStatus::Draft) {
                throw ValidationException::withMessages([
                    'version' => 'Only draft versions can be published.',
                ]);
            }

            $this->validateWorkflowGraph->execute($draft);

            // Archive the previous published version first to satisfy
            // the unique partial index allowing only one published version.
            $workflow->versions()
                ->where('status', WorkflowVersionStatus::Published->value)
                ->update([
                    'status' => WorkflowVersionStatus::Archived->value,
                    'updated_at' => now(),
                ]);

            $draft->status = WorkflowVersionStatus::Published;
            $draft->published_at = now();
            $draft->save();

            return $draft->fresh(['nodes', 'edges']);
        });
    }
}
