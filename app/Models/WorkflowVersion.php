<?php

namespace App\Models;

use App\Enums\WorkflowVersionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowVersion extends Model
{
    /** @use HasFactory<\Database\Factories\WorkflowVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'version_number',
        'status',
        'published_at'
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkflowVersionStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(WorkflowNode::class);
    }
}
