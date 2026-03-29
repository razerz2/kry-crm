<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.commercial.campaigns.create.title')
    </x-slot>

    <x-admin::form
        :action="route('admin.commercial.campaigns.store')"
        method="POST"
    >
        <div class="flex flex-col gap-4">
            {{-- Header --}}
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.commercial.campaigns.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <a href="{{ route('admin.commercial.campaigns.index') }}" class="transparent-button">
                        @lang('admin::app.commercial.campaigns.create.back-btn')
                    </a>

                    <button type="submit" class="primary-button">
                        @lang('admin::app.commercial.campaigns.create.save-btn')
                    </button>
                </div>
            </div>

            <div class="flex gap-2.5 max-xl:flex-wrap">
                {{-- Left: Campaign Details --}}
                <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                            @lang('admin::app.commercial.campaigns.create.general')
                        </p>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.commercial.campaigns.create.name')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    id="name"
                                    name="name"
                                    rules="required"
                                    :label="trans('admin::app.commercial.campaigns.create.name')"
                                    :placeholder="trans('admin::app.commercial.campaigns.create.name-placeholder')"
                                />

                                <x-admin::form.control-group.error control-name="name" />
                            </x-admin::form.control-group>
                        </div>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.commercial.campaigns.create.description')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="textarea"
                                    id="description"
                                    name="description"
                                    :placeholder="trans('admin::app.commercial.campaigns.create.description-placeholder')"
                                />
                            </x-admin::form.control-group>
                        </div>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.commercial.campaigns.create.channel')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="select"
                                    id="channel"
                                    name="channel"
                                    rules="required"
                                    :label="trans('admin::app.commercial.campaigns.create.channel')"
                                >
                                    <option value="email">@lang('admin::app.commercial.campaigns.channels.email')</option>
                                    <option value="whatsapp">@lang('admin::app.commercial.campaigns.channels.whatsapp')</option>
                                    <option value="both">@lang('admin::app.commercial.campaigns.channels.both')</option>
                                </x-admin::form.control-group.control>

                                <x-admin::form.control-group.error control-name="channel" />
                            </x-admin::form.control-group>
                        </div>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.commercial.campaigns.create.subject')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    id="subject"
                                    name="subject"
                                    :placeholder="trans('admin::app.commercial.campaigns.create.subject-placeholder')"
                                />
                            </x-admin::form.control-group>
                        </div>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.commercial.campaigns.create.message-body')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="textarea"
                                    id="message_body"
                                    name="message_body"
                                    rows="6"
                                    :placeholder="trans('admin::app.commercial.campaigns.create.message-body-placeholder')"
                                />
                            </x-admin::form.control-group>
                        </div>
                    </div>
                </div>

                {{-- Right: Filters + Template Variables --}}
                <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
                    @include('admin::commercial.campaigns.partials.filters', [
                        'filters'  => [],
                        'products' => $products,
                    ])

                    @include('admin::commercial.campaigns.partials.template-variables')
                </div>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
