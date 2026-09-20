<?php

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nodes' => ['required', 'array', 'min:1'],
            'nodes.*.node_key' => ['required', 'uuid'],
            'nodes.*.type' => ['required', 'string', 'in:trigger,action,end'],
            'nodes.*.configuration' => ['present', 'array'],
            'nodes.*.position' => ['required', 'array'],
            'nodes.*.position.x' => ['required', 'numeric'],
            'nodes.*.position.y' => ['required', 'numeric'],

            'edges' => ['required', 'array'],
            'edges.*.source_node_key' => ['required', 'uuid'],
            'edges.*.target_node_key' => ['required', 'uuid'],
            'edges.*.condition' => ['nullable', 'array'],
        ];
    }

    protected function after(): array
    {
        return [
            function ($validator) {
                $nodeKeys = collect($this->input('nodes', []))
                    ->pluck('node_key')
                    ->filter()
                    ->values();

                foreach ($this->input('edges', []) as $index => $edge) {
                    if (
                        isset($edge['source_node_key']) &&
                        ! $nodeKeys->contains($edge['source_node_key'])
                    ) {
                        $validator->errors()->add(
                            "edges.{$index}.source_node_key",
                            'The source node does not exist in the submitted nodes.'
                        );
                    }

                    if (
                        isset($edge['target_node_key']) &&
                        ! $nodeKeys->contains($edge['target_node_key'])
                    ) {
                        $validator->errors()->add(
                            "edges.{$index}.target_node_key",
                            'The target node does not exist in the submitted nodes.'
                        );
                    }
                }
            },
        ];
    }
}
