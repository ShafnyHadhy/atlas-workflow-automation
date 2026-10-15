<?php

namespace App\Actions\Workflows;

use App\Enums\WorkflowNodeType;
use App\Models\WorkflowVersion;
use Illuminate\Validation\ValidationException;

class ValidateWorkflowGraph
{
    public function execute(WorkflowVersion $version): void
    {
        $version->loadMissing(['nodes', 'edges']);

        $nodes = $version->nodes;
        $edges = $version->edges;

        if ($nodes->isEmpty()) {
            throw ValidationException::withMessages([
                'graph' => 'A workflow must contain at least one node.',
            ]);
        }

        $triggers = $nodes->where(
            'type',
            WorkflowNodeType::Trigger
        );

        $ends = $nodes->where(
            'type',
            WorkflowNodeType::End
        );

        if ($triggers->count() !== 1) {
            throw ValidationException::withMessages([
                'graph' => 'A workflow must contain exactly one Trigger node.',
            ]);
        }

        if ($ends->count() !== 1) {
            throw ValidationException::withMessages([
                'graph' => 'A workflow must contain exactly one End node.',
            ]);
        }

        $nodeKeys = $nodes->pluck('node_key')->all();

        $adjacency = array_fill_keys($nodeKeys, []);
        $reverseAdjacency = array_fill_keys($nodeKeys, []);
        $inDegree = array_fill_keys($nodeKeys, 0);

        foreach ($edges as $edge) {
            if (
                ! array_key_exists($edge->source_node_key, $adjacency) ||
                ! array_key_exists($edge->target_node_key, $adjacency)
            ) {
                throw ValidationException::withMessages([
                    'graph' => 'Every connection must reference nodes in this version.',
                ]);
            }

            if ($edge->source_node_key === $edge->target_node_key) {
                throw ValidationException::withMessages([
                    'graph' => 'A node cannot connect to itself.',
                ]);
            }

            $adjacency[$edge->source_node_key][] =
                $edge->target_node_key;

            $reverseAdjacency[$edge->target_node_key][] =
                $edge->source_node_key;

            $inDegree[$edge->target_node_key]++;
        }

        // Kahn's algorithm: a directed acyclic graph must
        // allow every node to be processed.
        $queue = [];

        foreach ($inDegree as $key => $degree) {
            if ($degree === 0) {
                $queue[] = $key;
            }
        }

        $processed = 0;

        while ($queue !== []) {
            $key = array_shift($queue);
            $processed++;

            foreach ($adjacency[$key] as $target) {
                $inDegree[$target]--;

                if ($inDegree[$target] === 0) {
                    $queue[] = $target;
                }
            }
        }

        if ($processed !== count($nodeKeys)) {
            throw ValidationException::withMessages([
                'graph' => 'Workflow graphs cannot contain cycles.',
            ]);
        }

        $triggerKey = $triggers->first()->node_key;
        $endKey = $ends->first()->node_key;

        $reachableFromTrigger = $this->reachable(
            $triggerKey,
            $adjacency
        );

        $canReachEnd = $this->reachable(
            $endKey,
            $reverseAdjacency
        );

        foreach ($nodeKeys as $key) {
            if (! isset($reachableFromTrigger[$key])) {
                throw ValidationException::withMessages([
                    'graph' => 'Every node must be reachable from the Trigger.',
                ]);
            }

            if (! isset($canReachEnd[$key])) {
                throw ValidationException::withMessages([
                    'graph' => 'Every node must have a path to the End node.',
                ]);
            }
        }
    }

    private function reachable(string $start, array $adjacency): array
    {
        $visited = [];
        $stack = [$start];

        while ($stack !== []) {
            $current = array_pop($stack);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            foreach ($adjacency[$current] as $next) {
                if (! isset($visited[$next])) {
                    $stack[] = $next;
                }
            }
        }

        return $visited;
    }
}
