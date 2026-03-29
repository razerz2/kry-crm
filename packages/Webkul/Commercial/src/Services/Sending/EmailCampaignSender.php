<?php

namespace Webkul\Commercial\Services\Sending;

use Illuminate\Support\Facades\Mail;
use Webkul\Commercial\Models\CommercialCampaignDelivery;

class EmailCampaignSender implements CampaignChannelSenderInterface
{
    public function send(CommercialCampaignDelivery $delivery): bool
    {
        if (empty($delivery->recipient_email)) {
            throw new \RuntimeException('Delivery has no recipient_email.');
        }

        $subject = $delivery->subject ?: 'Mensagem';
        $body = $delivery->rendered_message ?: '';

        Mail::raw($body, function ($message) use ($delivery, $subject) {
            $message->to($delivery->recipient_email, $delivery->recipient_name ?? '')
                ->subject($subject);
        });

        return true;
    }

    public function supports(string $channel, ?string $provider = null): bool
    {
        return $channel === 'email';
    }
}
