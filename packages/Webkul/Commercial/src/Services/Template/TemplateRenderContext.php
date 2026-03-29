<?php

namespace Webkul\Commercial\Services\Template;

use Webkul\Commercial\Models\CommercialCampaign;
use Webkul\Commercial\Models\CommercialCampaignAudience;

class TemplateRenderContext
{
    public function __construct(
        public readonly string $name,
        public readonly string $firstName,
        public readonly string $organization,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $entityType,
        public readonly string $products,
        public readonly string $commercialStatuses,
        public readonly string $channel,
        public readonly string $campaignName,
        public readonly string $campaignChannel,
    ) {}

    /**
     * Build a real context from a frozen audience member.
     */
    public static function fromAudienceMember(
        CommercialCampaignAudience $member,
        CommercialCampaign $campaign,
        string $channel
    ): self {
        $name      = $member->display_name ?? '';
        $firstName = trim(explode(' ', trim($name))[0] ?? $name);

        return new self(
            name:               $name,
            firstName:          $firstName,
            organization:       $member->organization_name ?? '',
            email:              $member->email ?? '',
            phone:              $member->phone ?? '',
            entityType:         $member->entity_type ?? '',
            products:           implode(', ', $member->crm_products ?? []),
            commercialStatuses: implode(', ', $member->commercial_statuses ?? []),
            channel:            $channel,
            campaignName:       $campaign->name,
            campaignChannel:    $campaign->channel,
        );
    }

    /**
     * Build a dummy context for preview when no audience member is available.
     */
    public static function dummy(CommercialCampaign $campaign): self
    {
        return new self(
            name:               'João Silva',
            firstName:          'João',
            organization:       'Empresa Exemplo Ltda',
            email:              'joao.silva@empresa.com',
            phone:              '+55 11 99999-9999',
            entityType:         'person',
            products:           'Produto A, Produto B',
            commercialStatuses: 'Ativo',
            channel:            $campaign->channel === 'both' ? 'email' : $campaign->channel,
            campaignName:       $campaign->name,
            campaignChannel:    $campaign->channel,
        );
    }

    /**
     * Return the variable map used by the renderer.
     */
    public function toVars(): array
    {
        return [
            'name'                => $this->name,
            'first_name'          => $this->firstName,
            'organization'        => $this->organization,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'entity_type'         => $this->entityType,
            'products'            => $this->products,
            'commercial_statuses' => $this->commercialStatuses,
            'channel'             => $this->channel,
            'campaign.name'       => $this->campaignName,
            'campaign.channel'    => $this->campaignChannel,
        ];
    }
}
