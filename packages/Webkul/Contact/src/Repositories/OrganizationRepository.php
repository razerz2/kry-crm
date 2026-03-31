<?php

namespace Webkul\Contact\Repositories;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Contracts\Organization;
use Webkul\Core\Eloquent\Repository;

class OrganizationRepository extends Repository
{
    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
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
        return Organization::class;
    }

    /**
     * Create.
     *
     * @return Organization
     */
    public function create(array $data)
    {
        $data = $this->sanitizeRequestedOrganizationData($data);

        if (array_key_exists('user_id', $data)) {
            $data['user_id'] = $data['user_id'] ?: null;
        }

        $organization = parent::create($this->extractModelData($data));

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $organization->id,
        ]));

        return $organization;
    }

    /**
     * Update.
     *
     * @param  int  $id
     * @param  array  $attribute
     * @return Organization
     */
    public function update(array $data, $id, $attributes = [])
    {
        $data = $this->sanitizeRequestedOrganizationData($data);

        if (array_key_exists('user_id', $data)) {
            $data['user_id'] = $data['user_id'] ?: null;
        }

        $organization = parent::update($this->extractModelData($data), $id);

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
                'entity_id' => $organization->id,
            ]), $attributes);

            return $organization;
        }

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $organization->id,
        ]));

        return $organization;
    }

    /**
     * Sanitize requested organization data and return a clean payload.
     */
    private function sanitizeRequestedOrganizationData(array $data): array
    {
        if (array_key_exists('emails', $data)) {
            $data['emails'] = $this->sanitizeContactEntries($data['emails']);
        }

        if (array_key_exists('contact_numbers', $data)) {
            $data['contact_numbers'] = $this->sanitizeContactEntries($data['contact_numbers']);
        }

        return $data;
    }

    /**
     * Keep only model columns that are guaranteed to exist.
     */
    private function extractModelData(array $data): array
    {
        if (! Schema::hasColumn('organizations', 'emails')) {
            unset($data['emails']);
        }

        if (! Schema::hasColumn('organizations', 'contact_numbers')) {
            unset($data['contact_numbers']);
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

    /**
     * Delete organization and it's persons.
     *
     * @param  int  $id
     * @return @void
     */
    public function delete($id)
    {
        $organization = $this->findOrFail($id);

        DB::transaction(function () use ($organization, $id) {
            $this->attributeValueRepository->deleteWhere([
                'entity_id' => $id,
                'entity_type' => 'organizations',
            ]);

            $organization->delete();
        });
    }
}
