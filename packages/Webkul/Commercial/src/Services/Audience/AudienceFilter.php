<?php

namespace Webkul\Commercial\Services\Audience;

class AudienceFilter
{
    /**
     * Supported entity type constants.
     */
    public const ENTITY_PERSON = 'person';

    public const ENTITY_ORGANIZATION = 'organization';

    public const ENTITY_BOTH = 'both';

    /**
     * Supported segment constants.
     */
    public const SEGMENT_CUSTOMER_ANY = 'customer_any';

    public const SEGMENT_NON_CUSTOMER = 'non_customer';

    public const SEGMENT_HAS_LINK = 'has_link';

    public const SEGMENT_NO_LINK = 'no_link';

    /**
     * Supported channel constants.
     */
    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_BOTH = 'both';

    public function __construct(
        /**
         * Entity types to include: 'person', 'organization', 'both'.
         */
        public string $entityType = self::ENTITY_BOTH,

        /**
         * Filter by specific CRM product IDs.
         * Empty = all products.
         */
        public array $crmProductIds = [],

        /**
         * Filter by specific commercial statuses (AccountProductStatus values).
         * Empty = all statuses.
         */
        public array $commercialStatuses = [],

        /**
         * Segment filter: customer_any, non_customer, has_link, no_link.
         * Null = no segment filter.
         */
        public ?string $segment = null,

        /**
         * Channel filter: email, whatsapp, both.
         * Null = no channel filter.
         */
        public ?string $channel = null,

        /**
         * Only include entities with a valid email.
         */
        public bool $onlyWithEmail = false,

        /**
         * Only include entities with a valid phone/WhatsApp number.
         */
        public bool $onlyWithPhone = false,

        /**
         * For organizations: only use the primary contact person for email/phone.
         */
        public bool $onlyPrimaryContactIfOrganization = false,

        /**
         * Include inactive_customer in results.
         */
        public bool $includeInactiveCustomer = true,

        /**
         * Include former_customer in results.
         */
        public bool $includeFormerCustomer = true,

        /**
         * Free-text search by name, email or phone.
         */
        public ?string $search = null,

        /**
         * Maximum number of results (0 = no limit).
         */
        public int $limit = 0,
    ) {}

    /**
     * Create from an associative array (e.g. from artisan command options).
     */
    public static function fromArray(array $data): static
    {
        return new static(
            entityType: $data['entity_type'] ?? self::ENTITY_BOTH,
            crmProductIds: $data['crm_product_ids'] ?? [],
            commercialStatuses: $data['commercial_statuses'] ?? [],
            segment: $data['segment'] ?? null,
            channel: $data['channel'] ?? null,
            onlyWithEmail: (bool) ($data['only_with_email'] ?? false),
            onlyWithPhone: (bool) ($data['only_with_phone'] ?? false),
            onlyPrimaryContactIfOrganization: (bool) ($data['only_primary_contact_if_organization'] ?? false),
            includeInactiveCustomer: (bool) ($data['include_inactive_customer'] ?? true),
            includeFormerCustomer: (bool) ($data['include_former_customer'] ?? true),
            search: $data['search'] ?? null,
            limit: (int) ($data['limit'] ?? 0),
        );
    }

    /**
     * Whether persons should be queried.
     */
    public function includesPersons(): bool
    {
        return in_array($this->entityType, [self::ENTITY_PERSON, self::ENTITY_BOTH]);
    }

    /**
     * Whether organizations should be queried.
     */
    public function includesOrganizations(): bool
    {
        return in_array($this->entityType, [self::ENTITY_ORGANIZATION, self::ENTITY_BOTH]);
    }

    /**
     * Whether to filter by email channel.
     */
    public function requiresEmail(): bool
    {
        return $this->onlyWithEmail || in_array($this->channel, [self::CHANNEL_EMAIL, self::CHANNEL_BOTH]);
    }

    /**
     * Whether to filter by phone/whatsapp channel.
     */
    public function requiresPhone(): bool
    {
        return $this->onlyWithPhone || in_array($this->channel, [self::CHANNEL_WHATSAPP, self::CHANNEL_BOTH]);
    }
}
