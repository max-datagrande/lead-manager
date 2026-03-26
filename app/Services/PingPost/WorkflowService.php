<?php

namespace App\Services\PingPost;

use App\Models\Buyer;
use App\Models\Workflow;
use App\Models\WorkflowBuyer;

class WorkflowService
{
    public function create(array $data): Workflow
    {
        return Workflow::create($data);
    }

    public function update(Workflow $workflow, array $data): Workflow
    {
        $workflow->update($data);

        return $workflow->fresh();
    }

    public function delete(Workflow $workflow): void
    {
        $workflow->delete();
    }

    /**
     * Sync the buyer list for a workflow.
     *
     * @param  array<int, array{buyer_id: int, position: int, is_fallback?: bool, buyer_group?: string, is_active?: bool}>  $buyers
     */
    public function syncBuyers(Workflow $workflow, array $buyers): void
    {
        $buyerIds = array_column($buyers, 'buyer_id');
        $integrationMap = Buyer::whereIn('id', $buyerIds)->pluck('integration_id', 'id');

        $workflow->workflowBuyers()->delete();

        foreach ($buyers as $buyer) {
            $integrationId = $integrationMap[$buyer['buyer_id']] ?? null;

            if (! $integrationId) {
                continue;
            }

            WorkflowBuyer::create([
                'workflow_id' => $workflow->id,
                'integration_id' => $integrationId,
                'position' => $buyer['position'],
                'is_fallback' => $buyer['is_fallback'] ?? false,
                'buyer_group' => $buyer['buyer_group'] ?? 'primary',
                'is_active' => $buyer['is_active'] ?? true,
            ]);
        }
    }

    public function duplicate(Workflow $workflow): Workflow
    {
        $clone = $workflow->replicate(['id', 'created_at', 'updated_at']);
        $clone->name = $workflow->name.' (Copy)';
        $clone->is_active = false;
        $clone->save();

        foreach ($workflow->workflowBuyers as $buyer) {
            WorkflowBuyer::create([
                'workflow_id' => $clone->id,
                'integration_id' => $buyer->integration_id,
                'position' => $buyer->position,
                'is_fallback' => $buyer->is_fallback,
                'buyer_group' => $buyer->buyer_group,
                'is_active' => $buyer->is_active,
            ]);
        }

        return $clone;
    }
}
