<?php

namespace App\Actions\Workflows;

use App\Enums\WorkflowVersionStatus;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateWorkflowDraft
{
    public function execute(Workflow $workflow): WorkflowVersion
    {
        return DB::transaction(function () use ($workflow) {
            $lockedWorkflow = Workflow::query()
                ->whereKey($workflow->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedWorkflow->versions()
                    ->where('status', WorkflowVersionStatus::Draft->value)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'version' => 'This workflow already has a draft version.',
                ]);
            }

            $published = $lockedWorkflow->versions()
                ->where('status', WorkflowVersionStatus::Published->value)
                ->first();

            if (! $published) {
                throw ValidationException::withMessages([
                    'version' => 'Publish the initial version before creating another draft.',
                ]);
            }

            $published->load(['nodes', 'edges']);

            $versionNumber = (int) $lockedWorkflow->versions()
                ->max('version_number') + 1;

            $draft = $lockedWorkflow->versions()->create([
                'version_number' => $versionNumber,
                'status' => WorkflowVersionStatus::Draft,
                'published_at' => null,
            ]);

            $nodeKeyMap = [];

            foreach ($published->nodes as $node) {
                $newKey = (string) Str::uuid();

                $nodeKeyMap[$node->node_key] = $newKey;

                $draft->nodes()->create([
                    'node_key' => $newKey,
                    'type' => $node->type,
                    'configuration' => $node->configuration,
                    'position' => $node->position,
                ]);
            }

            foreach ($published->edges as $edge) {
                $draft->edges()->create([
                    'source_node_key' => $nodeKeyMap[$edge->source_node_key],
                    'target_node_key' => $nodeKeyMap[$edge->target_node_key],
                    'condition' => $edge->condition,
                ]);
            }

            return $draft->load(['nodes', 'edges']);
        });
    }
}
