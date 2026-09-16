<?php

namespace App\Models;

use App\Enums\WorkflowVersionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workflow extends Model
{
    /** @use HasFactory<\Database\Factories\WorkflowFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'description'
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function draftVersion(): HasOne
    {
        return $this->hasOne(WorkflowVersion::class)
            ->where('status', WorkflowVersionStatus::Draft);
    }
}
