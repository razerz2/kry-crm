<?php

namespace Webkul\Commercial\Services\Audience;

class AudienceItem
{
    public function __construct(
        /**
         * Morph class: Person or Organization model class.
         */
        public string $entityType,

        /**
         * Primary key of the entity.
         */
        public int $entityId,

        /**
         * Human-readable entity type label.
         */
        public string $entityLabel,

        /**
         * Display name (person name or organization name).
         */
        public string $displayName,

        /**
         * Organization name (for persons linked to an org; null for standalone persons or orgs themselves).
         */
        public ?string $organizationName = null,

        /**
         * Primary email address (first valid from JSON array).
         */
        public ?string $email = null,

        /**
         * Primary phone number (first valid from JSON array).
         */
        public ?string $phone = null,

        /**
         * List of CRM product names linked to this entity.
         */
        public array $crmProducts = [],

        /**
         * List of commercial status values for this entity.
         */
        public array $commercialStatuses = [],

        /**
         * Available communication channels: 'email', 'whatsapp'.
         */
        public array $availableChannels = [],

        /**
         * Compact commercial summary string.
         * e.g. "AllSync: Cliente, OtherProduct: Prospect"
         */
        public string $sourceSummary = '',
    ) {}

    /**
     * Check if this item can receive email.
     */
    public function hasEmail(): bool
    {
        return ! empty($this->email);
    }

    /**
     * Check if this item can receive WhatsApp.
     */
    public function hasPhone(): bool
    {
        return ! empty($this->phone);
    }

    /**
     * Convert to array for serialization / preview.
     */
    public function toArray(): array
    {
        return [
            'entity_type'         => $this->entityLabel,
            'entity_id'           => $this->entityId,
            'display_name'        => $this->displayName,
            'organization_name'   => $this->organizationName,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'crm_products'        => $this->crmProducts,
            'commercial_statuses' => $this->commercialStatuses,
            'available_channels'  => $this->availableChannels,
            'source_summary'      => $this->sourceSummary,
        ];
    }
}
