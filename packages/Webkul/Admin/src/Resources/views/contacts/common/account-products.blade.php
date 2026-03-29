@php
    use Webkul\Commercial\Enums\AccountProductStatus;

    $statusColors = [
        'lead'              => 'bg-blue-100 text-blue-700',
        'prospect'          => 'bg-purple-100 text-purple-700',
        'opportunity'       => 'bg-yellow-100 text-yellow-700',
        'customer'          => 'bg-green-100 text-green-700',
        'inactive_customer' => 'bg-gray-200 text-gray-600',
        'former_customer'   => 'bg-orange-100 text-orange-600',
        'lost'              => 'bg-red-100 text-red-600',
    ];

    $existingLinks = $entity->accountProducts()->with('crmProduct', 'user')->get();
@endphp

<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
    <x-admin::accordion class="select-none !border-none">
        <x-slot:header class="!p-0">
            <h4 class="font-semibold dark:text-white">
                Relacionamento Comercial
            </h4>
        </x-slot>

        <x-slot:content class="mt-4 !px-0 !pb-0">

            {{-- Vínculos existentes --}}
            @if ($existingLinks->isNotEmpty())
                <div class="mb-4 flex flex-col gap-2">
                    @foreach ($existingLinks as $link)
                        <div
                            class="flex flex-col gap-2 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
                            x-data="{ editing: false }"
                        >
                            {{-- View mode --}}
                            <div class="flex items-center justify-between" x-show="! editing">
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-sm font-semibold dark:text-white">
                                        {{ $link->crmProduct->name }}
                                    </span>
                                    <span class="inline-block self-start rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$link->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ $link->status->label() }}
                                    </span>
                                    @if ($link->user)
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            Resp.: {{ $link->user->name }}
                                        </span>
                                    @endif
                                    @if ($link->notes)
                                        <span class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $link->notes }}</span>
                                    @endif
                                </div>

                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="icon-edit rounded p-1 text-lg text-gray-500 transition hover:bg-gray-200 dark:hover:bg-gray-700"
                                        title="Editar"
                                        @click="editing = true"
                                    ></button>
                                    <button
                                        type="button"
                                        class="icon-delete rounded p-1 text-lg text-red-400 transition hover:bg-red-50 dark:hover:bg-gray-700"
                                        title="Remover"
                                        onclick="window.deleteAccountProduct({{ $link->id }})"
                                    ></button>
                                </div>
                            </div>

                            {{-- Edit mode --}}
                            <div x-show="editing" x-cloak>
                                @include('admin::contacts.common.account-product-form', [
                                    'formId'       => 'edit-link-' . $link->id,
                                    'entityType'   => $entityType,
                                    'entityId'     => $entity->id,
                                    'crmProducts'  => $crmProducts,
                                    'existingLink' => $link,
                                    'submitLabel'  => 'Salvar',
                                    'cancelAlpine' => 'editing = false',
                                ])
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mb-3 text-sm text-gray-400 dark:text-gray-500">
                    Nenhum produto vinculado ainda.
                </p>
            @endif

            {{-- Novo vínculo --}}
            <div x-data="{ open: false }">
                <button
                    type="button"
                    class="flex items-center gap-1 text-sm font-medium text-brandColor hover:underline"
                    @click="open = ! open"
                >
                    <span class="icon-add text-lg"></span>
                    Adicionar produto
                </button>

                <div class="mt-2" x-show="open" x-cloak>
                    @include('admin::contacts.common.account-product-form', [
                        'formId'       => 'new-link-' . $entity->id,
                        'entityType'   => $entityType,
                        'entityId'     => $entity->id,
                        'crmProducts'  => $crmProducts,
                        'existingLink' => null,
                        'submitLabel'  => 'Vincular',
                        'cancelAlpine' => 'open = false',
                    ])
                </div>
            </div>

        </x-slot>
    </x-admin::accordion>
</div>
