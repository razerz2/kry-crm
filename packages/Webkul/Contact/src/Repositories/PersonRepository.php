<?php

namespace Webkul\Contact\Repositories;

use Illuminate\Container\Container;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Contracts\Person;
use Webkul\Core\Eloquent\Repository;

class PersonRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'name',
        'emails',
        'contact_numbers',
        'organization_id',
        'job_title',
        'organization.name',
        'user_id',
        'user.name',
    ];

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        protected OrganizationRepository $organizationRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return Person::class;
    }

    /**
     * Create.
     *
     * @return Person
     */
    public function create(array $data)
    {
        $data = $this->sanitizeRequestedPersonData($data);

        if (! empty($data['organization_name'])) {
            $organization = $this->fetchOrCreateOrganizationByName($data['organization_name']);

            $data['organization_id'] = $organization->id;
        }

        if (array_key_exists('user_id', $data)) {
            $data['user_id'] = $data['user_id'] ?: null;
        }

        $person = parent::create($data);

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $person->id,
        ]));

        return $person;
    }

    /**
     * Update.
     *
     * @return Person
     */
    public function update(array $data, $id, $attributes = [])
    {
        $existingPerson = $this->findOrFail($id);

        $data = $this->sanitizeRequestedPersonData($data, $existingPerson);

        if (array_key_exists('user_id', $data)) {
            $data['user_id'] = empty($data['user_id']) ? null : $data['user_id'];
        }

        if (! empty($data['organization_name'])) {
            $organization = $this->fetchOrCreateOrganizationByName($data['organization_name']);

            $data['organization_id'] = $organization->id;

            unset($data['organization_name']);
        }

        $person = parent::update($data, $id);

        /**
         * If attributes are provided then only save the provided attributes and return.
         */
        if (! empty($attributes)) {
            $conditions = ['entity_type' => $data['entity_type']];

            if (isset($data['quick_add'])) {
                $conditions['quick_add'] = 1;
            }

            $attributes = $this->attributeRepository->where($conditions)
                ->whereIn('code', $attributes)
                ->get();

            $this->attributeValueRepository->save(array_merge($data, [
                'entity_id' => $person->id,
            ]), $attributes);

            return $person;
        }

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $person->id,
        ]));

        return $person;
    }

    /**
     * Retrieves customers count based on date.
     *
     * @return int
     */
    public function getCustomerCount($startDate, $endDate)
    {
        return $this
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->count();
    }

    /**
     * Fetch or create an organization.
     */
    public function fetchOrCreateOrganizationByName(string $organizationName)
    {
        $organization = $this->organizationRepository->findOneWhere([
            'name' => $organizationName,
        ]);

        return $organization ?: $this->organizationRepository->create([
            'entity_type' => 'organizations',
            'name' => $organizationName,
        ]);
    }

    /**
     * Sanitize requested person data and return the clean array.
     */
    private function sanitizeRequestedPersonData(array $data, ?Person $existingPerson = null): array
    {
        if (array_key_exists('emails', $data)) {
            $data['emails'] = $this->sanitizeContactEntries($data['emails']);
        }

        if (array_key_exists('contact_numbers', $data)) {
            $data['contact_numbers'] = $this->sanitizeContactEntries($data['contact_numbers']);
        }

        if (
            array_key_exists('organization_id', $data)
            && empty($data['organization_id'])
        ) {
            $data['organization_id'] = null;
        }

        $resolvedUserId = array_key_exists('user_id', $data)
            ? $data['user_id']
            : $existingPerson?->user_id;

        $resolvedOrganizationId = array_key_exists('organization_id', $data)
            ? $data['organization_id']
            : $existingPerson?->organization_id;

        $resolvedEmails = array_key_exists('emails', $data)
            ? $data['emails']
            : $this->sanitizeContactEntries($existingPerson?->emails);

        $resolvedContactNumbers = array_key_exists('contact_numbers', $data)
            ? $data['contact_numbers']
            : $this->sanitizeContactEntries($existingPerson?->contact_numbers);

        $uniqueIdParts = array_filter([
            $resolvedUserId,
            $resolvedOrganizationId,
            $resolvedEmails[0]['value'] ?? null,
        ]);

        $data['unique_id'] = implode('|', $uniqueIdParts);

        if (! empty($resolvedContactNumbers)) {
            $data['unique_id'] .= '|'.$resolvedContactNumbers[0]['value'];
        }

        return $data;
    }

    /**
     * Normalize email/phone entries to [{value, label}] format.
     */
    private function sanitizeContactEntries(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        return collect($entries)
            ->map(function ($entry) {
                if (is_string($entry)) {
                    $value = trim($entry);

                    return $value === ''
                        ? null
                        : [
                            'value' => $value,
                            'label' => 'work',
                        ];
                }

                if (! is_array($entry)) {
                    return null;
                }

                $value = trim((string) ($entry['value'] ?? ''));

                if ($value === '') {
                    return null;
                }

                return [
                    'value' => $value,
                    'label' => $entry['label'] ?? 'work',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
