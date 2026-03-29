<?php

namespace Webkul\Commercial\Repositories;

use Webkul\Commercial\Models\CommercialCampaignDeliveryProxy;
use Webkul\Core\Eloquent\Repository;

class CommercialCampaignDeliveryRepository extends Repository
{
    public function model(): string
    {
        return CommercialCampaignDeliveryProxy::modelClass();
    }

    /**
     * Aggregate status counts for a campaign.
     *
     * @return array{pending:int, queued:int, sending:int, sent:int, failed:int, skipped:int, canceled:int, total:int}
     */
    public function statsByCampaign(int $campaignId): array
    {
        $rows = $this->model
            ->where('commercial_campaign_id', $campaignId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = ['pending', 'queued', 'sending', 'sent', 'failed', 'skipped', 'canceled'];
        $result = [];
        foreach ($statuses as $s) {
            $result[$s] = (int) ($rows[$s] ?? 0);
        }
        $result['total'] = array_sum($result);

        return $result;
    }

    /**
     * Count deliveries still being processed.
     */
    public function countInProgress(int $campaignId): int
    {
        return $this->model
            ->where('commercial_campaign_id', $campaignId)
            ->whereIn('status', ['pending', 'queued', 'sending'])
            ->count();
    }
}
