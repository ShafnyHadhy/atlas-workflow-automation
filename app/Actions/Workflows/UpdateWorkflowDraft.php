<?php

namespace App\Actions\Workflows;

use App\Enums\WorkflowVersionStatus;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;

class UpdateWorkflowDraft
{
    public function execute(
        WorkflowVersion $version,
        array $nodes,
        array $edges,
    ): bool {

        return DB::transaction(function () use ($version, $nodes, $edges) {

            if ($version->status !== WorkflowVersionStatus::Draft) {
                throw new \LogicException(
                    'Only draft workflow versions can be updated.'
                );
            }

            $version->nodes()->delete();
            $version->edges()->delete();

            foreach ($nodes as $node) {
                $version->nodes()->create($node);
            }

            foreach ($edges as $edge) {
                $version->edges()->create($edge);
            }

            return true;
        });
    }
}
