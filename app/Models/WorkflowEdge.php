<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowEdge extends Model
{
    /** @use HasFactory<\Database\Factories\WorkflowEdgeFactory> */
    use HasFactory;

    protected $fillable = [
        'workflow_version_id',
        'source_node_key',
        'target_node_key',
        'condition',
    ];

    protected function casts(): array
    {
        return [
            'condition' => 'array',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }
}
