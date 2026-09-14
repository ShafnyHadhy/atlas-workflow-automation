<?php

namespace App\Models;

use App\Enums\WorkflowNodeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowNode extends Model
{
    /** @use HasFactory<\Database\Factories\WorkflowNodeFactory> */
    use HasFactory;

    protected $fillable = [
        'workflow_version_id',
        'node_key',
        'type',
        'configuration',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkflowNodeType::class,
            'configuration' => 'array',
            'position' => 'array',
        ];
    }

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }
}
