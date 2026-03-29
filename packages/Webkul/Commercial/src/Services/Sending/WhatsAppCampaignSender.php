<?php

namespace Webkul\Commercial\Services\Sending;

use Illuminate\Support\Facades\Log;
use Webkul\Commercial\Models\CommercialCampaignDelivery;

/**
 * WhatsApp sender stub.
 *
 * The `send()` method is intentionally NOT implemented for real delivery yet.
 * It logs the attempt and throws, so deliveries are recorded as `failed`
 * with a clear reason — keeping the audit trail intact.
 *
 * To add a real provider, implement the matching protected method and call it
 * from `send()` based on `$delivery->provider`:
 *
 *   match($delivery->provider) {
 *       'meta_official' => $this->sendViaMetaOfficial($delivery),
 *       'waha'          => $this->sendViaWaha($delivery),
 *       'evolution'     => $this->sendViaEvolution($delivery),
 *       default         => throw new \RuntimeException("Unknown provider: {$delivery->provider}"),
 *   };
 */
class WhatsAppCampaignSender implements CampaignChannelSenderInterface
{
    public function send(CommercialCampaignDelivery $delivery): bool
    {
        Log::channel('stack')->info('[WhatsApp Stub] Would send campaign delivery', [
            'delivery_id' => $delivery->id,
            'campaign_id' => $delivery->commercial_campaign_id,
            'provider'    => $delivery->provider,
            'phone'       => $delivery->recipient_phone,
        ]);

        // TODO: when providers are integrated, replace this with real dispatch:
        // match ($delivery->provider) {
        //     'meta_official' => $this->sendViaMetaOfficial($delivery),
        //     'waha'          => $this->sendViaWaha($delivery),
        //     'evolution'     => $this->sendViaEvolution($delivery),
        //     default         => throw new \RuntimeException("Unknown WhatsApp provider: {$delivery->provider}"),
        // };

        throw new \RuntimeException(
            'WhatsApp provider not yet configured. Provider: ' . ($delivery->provider ?? 'none')
        );
    }

    public function supports(string $channel, ?string $provider = null): bool
    {
        return $channel === 'whatsapp';
    }

    // ── Future provider methods ──────────────────────────────────

    // protected function sendViaMetaOfficial(CommercialCampaignDelivery $delivery): bool { ... }
    // protected function sendViaWaha(CommercialCampaignDelivery $delivery): bool { ... }
    // protected function sendViaEvolution(CommercialCampaignDelivery $delivery): bool { ... }
}
