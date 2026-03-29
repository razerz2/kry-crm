<?php

namespace Webkul\Commercial\Services\Sending;

use Webkul\Commercial\Models\CommercialCampaignDelivery;

interface CampaignChannelSenderInterface
{
    /**
     * Attempt to send a single delivery.
     *
     * Returns true on success.
     * Must throw a \Throwable on failure — the caller handles logging and status update.
     */
    public function send(CommercialCampaignDelivery $delivery): bool;

    /**
     * Returns true if this sender handles the given channel (and, optionally, provider).
     */
    public function supports(string $channel, ?string $provider = null): bool;
}
