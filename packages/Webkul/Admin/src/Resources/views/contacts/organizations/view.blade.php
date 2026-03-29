<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.contacts.organizations.view.title', ['name' => $organization->name])
    </x-slot>

    <div class="flex gap-4 max-lg:flex-wrap">
        <!-- Left Panel -->
        <div class="max-lg:min-w-full max-lg:max-w-full lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 [&>div:last-child]:border-b-0">

            <!-- Header -->
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <div class="flex items-center justify-between">
                    <x-admin::breadcrumbs
                        name="contacts.organizations.view"
                        :entity="$organization"
                    />

                    <a
                        href="{{ route('admin.contacts.organizations.edit', $organization->id) }}"
                        class="icon-edit rounded-md p-1 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                        title="Editar"
                    ></a>
                </div>

                <div class="mb-2 flex flex-col gap-0.5">
                    <h3 class="text-lg font-bold dark:text-white">
                        {{ $organization->name }}
                    </h3>

                    @if ($organization->trade_name)
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $organization->trade_name }}
                        </p>
                    @endif
                </div>
            </div>

            <!-- Fiscal info -->
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <x-admin::accordion class="select-none !border-none">
                    <x-slot:header class="!p-0">
                        <h4 class="font-semibold dark:text-white">Dados da Empresa</h4>
                    </x-slot>

                    <x-slot:content class="mt-3 !px-0 !pb-0">
                        <div class="flex flex-col gap-2">
                            @if ($organization->cnpj)
                                <div class="grid grid-cols-[1fr_2fr] items-center gap-1">
                                    <div class="label dark:text-white">CNPJ</div>
                                    <div class="font-medium dark:text-white">{{ $organization->cnpj }}</div>
                                </div>
                            @endif

                            @if ($organization->legal_name)
                                <div class="grid grid-cols-[1fr_2fr] items-center gap-1">
                                    <div class="label dark:text-white">Razão Social</div>
                                    <div class="font-medium dark:text-white">{{ $organization->legal_name }}</div>
                                </div>
                            @endif

                            @if ($organization->trade_name)
                                <div class="grid grid-cols-[1fr_2fr] items-center gap-1">
                                    <div class="label dark:text-white">Nome Fantasia</div>
                                    <div class="font-medium dark:text-white">{{ $organization->trade_name }}</div>
                                </div>
                            @endif

                            @if ($organization->address)
                                <div class="grid grid-cols-[1fr_2fr] items-start gap-1">
                                    <div class="label dark:text-white">Endereço</div>
                                    <div class="flex flex-col gap-0.5 font-medium dark:text-white">
                                        @isset($organization->address['address'])
                                            <span>{{ $organization->address['address'] }}</span>
                                        @endisset
                                        @if (isset($organization->address['postcode']) && isset($organization->address['city']))
                                            <span>{{ $organization->address['postcode'] . '  ' . $organization->address['city'] }}</span>
                                        @endif
                                        @isset($organization->address['country'])
                                            <span>{{ core()->country_name($organization->address['country']) }}</span>
                                        @endisset
                                    </div>
                                </div>
                            @endif

                            @if (! $organization->cnpj && ! $organization->legal_name && ! $organization->trade_name && ! $organization->address)
                                <p class="text-sm text-gray-400 dark:text-gray-500">Nenhum dado cadastrado.</p>
                            @endif
                        </div>
                    </x-slot>
                </x-admin::accordion>
            </div>

            <!-- Persons vinculadas -->
            @if ($organization->persons->isNotEmpty())
                <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                    <x-admin::accordion class="select-none !border-none">
                        <x-slot:header class="!p-0">
                            <h4 class="font-semibold dark:text-white">
                                Contatos ({{ $organization->persons->count() }})
                            </h4>
                        </x-slot>

                        <x-slot:content class="mt-3 !px-0 !pb-0">
                            <div class="flex flex-col gap-2">
                                @foreach ($organization->persons as $person)
                                    <a
                                        href="{{ route('admin.contacts.persons.view', $person->id) }}"
                                        class="flex items-center gap-2 rounded-lg p-1 transition hover:bg-gray-50 dark:hover:bg-gray-800"
                                    >
                                        <x-admin::avatar :name="$person->name" size="sm" />
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-brandColor">{{ $person->name }}</span>
                                            @if ($person->job_title)
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $person->job_title }}</span>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </x-slot>
                    </x-admin::accordion>
                </div>
            @endif

            <!-- Relacionamento Comercial -->
            @include('admin::contacts.common.account-products', [
                'entity'      => $organization,
                'entityType'  => 'organizations',
                'crmProducts' => $crmProducts,
            ])
        </div>

        <!-- Right Panel (activities) -->
        <div class="flex w-full flex-col gap-4 rounded-lg">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h4 class="mb-2 font-semibold dark:text-white">Pessoas vinculadas</h4>
                @if ($organization->persons->isEmpty())
                    <p class="text-sm text-gray-400 dark:text-gray-500">Nenhum contato vinculado.</p>
                @else
                    <div class="flex flex-col gap-1">
                        @foreach ($organization->persons as $person)
                            <a
                                href="{{ route('admin.contacts.persons.view', $person->id) }}"
                                class="text-sm text-brandColor hover:underline"
                            >
                                {{ $person->name }}
                                @if ($person->job_title) — {{ $person->job_title }} @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin::layouts>
