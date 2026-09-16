<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,

            'draft_version' => $this->whenLoaded('draftVersion', function () {
                return [
                    'id' => $this->draftVersion->id,
                    'version_number' => $this->draftVersion->version_number,
                    'status' => $this->draftVersion->status->value,

                    'nodes' => $this->draftVersion->nodes->map(fn ($node) => [
                        'id' => $node->id,
                        'node_key' => $node->node_key,
                        'type' => $node->type->value,
                        'configuration' => $node->configuration,
                        'position' => $node->position,
                    ])->values(),

                    'edges' => $this->draftVersion->edges->map(fn ($edge) => [
                        'id' => $edge->id,
                        'source_node_key' => $edge->source_node_key,
                        'target_node_key' => $edge->target_node_key,
                        'condition' => $edge->condition,
                    ])->values(),
                ];
            }),
        ];
    }
}
