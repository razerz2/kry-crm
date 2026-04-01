@php
    $activeConfiguration = system_config()->getActiveConfigurationItem();

    $name = $activeConfiguration->getName();

    $configurationSections = $activeConfiguration->getChildren()->isNotEmpty()
        ? $activeConfiguration->getChildren()
        : collect([$activeConfiguration]);
@endphp

<x-admin::layouts>
    <x-slot:title>
        {{ strip_tags($name) }}
    </x-slot>

    {!! view_render_event('admin.configuration.edit.form_controls.before') !!}

    <!-- Configuration form fields -->
    <x-admin::form
        action=""
        enctype="multipart/form-data"
    >
        <!-- Save Inventory -->
        <div class="mt-3.5 flex items-center justify-between gap-4 max-sm:flex-wrap">
            <p class="text-xl font-bold text-gray-800 dark:text-white">
                {{ $name }}
            </p>

            <!-- Save Inventory -->
            <div class="flex items-center gap-x-2.5">
                {!! view_render_event('admin.configuration.edit.back_button.before') !!}

                <!-- Back Button -->
                <a
                    href="{{ route('admin.configuration.index') }}"
                    class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800"
                >
                    @lang('admin::app.configuration.index.back')
                </a>

                {!! view_render_event('admin.configuration.edit.back_button.after') !!}

                @if ($activeConfiguration?->getKey() === 'email.smtp')
                    <v-smtp-configuration-test>
                        <button
                            type="button"
                            class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800"
                        >
                            @lang('admin::app.configuration.index.email.smtp.test.test-btn')
                        </button>
                    </v-smtp-configuration-test>
                @endif

                @if ($activeConfiguration?->getKey() === 'whatsapp')
                    <v-whatsapp-configuration-test>
                        <button
                            type="button"
                            class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800"
                        >
                            @lang('admin::app.configuration.index.whatsapp.test.test-btn')
                        </button>
                    </v-whatsapp-configuration-test>

                    <v-whatsapp-test-message>
                        <button
                            type="button"
                            class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800"
                        >
                            @lang('admin::app.configuration.index.whatsapp.test-message.test-btn')
                        </button>
                    </v-whatsapp-test-message>
                @endif

                {!! view_render_event('admin.configuration.edit.save_button.before') !!}

                <button
                    type="submit"
                    class="primary-button"
                >
                    @lang('admin::app.configuration.index.save-btn')
                </button>

                {!! view_render_event('admin.configuration.edit.save_button.after') !!}
            </div>
        </div>

        <div class="grid grid-cols-[1fr_2fr] gap-10 max-lg:grid-cols-1 max-lg:gap-4 lg:mt-6">
            @foreach ($configurationSections as $child)
                <div class="grid content-start gap-2.5 max-lg:mt-6">
                    <p class="text-base font-semibold text-gray-600 dark:text-gray-300">
                        {{ $child->getName() }}
                    </p>

                    <p class="leading-[140%] text-gray-600 dark:text-gray-300">
                        {!! $child->getInfo() !!}
                    </p>
                </div>

                <div class="box-shadow rounded bg-white p-4 dark:bg-gray-900">
                    {!! view_render_event('admin.configuration.edit.form_controls.before') !!}

                    @foreach ($child->getFields() as $field)
                        @if (
                            $field->getType() == 'blade'
                            && view()->exists($path = $field->getPath())
                        )
                            {!! view($path, compact('field', 'child'))->render() !!}
                        @else 
                            @include ('admin::configuration.field-type')
                        @endif
                    @endforeach

                    {!! view_render_event('admin.configuration.edit.form_controls.after') !!}
                </div>
            @endforeach
        </div>
    </x-admin::form>

    {!! view_render_event('admin.configuration.edit.form_controls.after') !!}

    @if ($activeConfiguration?->getKey() === 'email.smtp')
        @pushOnce('scripts')
            <script
                type="text/x-template"
                id="v-smtp-configuration-test-template"
            >
                <div>
                    <div @click="$refs.smtpTestModal.open()">
                        <slot></slot>
                    </div>

                    <x-admin::modal ref="smtpTestModal">
                        <x-slot:header>
                            <p class="text-lg font-semibold text-gray-800 dark:text-white">
                                @lang('admin::app.configuration.index.email.smtp.test.modal-title')
                            </p>
                        </x-slot>

                        <x-slot:content>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                @lang('admin::app.configuration.index.email.smtp.test.modal-info')
                            </p>

                            <div class="mt-4">
                                <label
                                    for="smtp_test_email"
                                    class="required mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
                                >
                                    @lang('admin::app.configuration.index.email.smtp.test.recipient')
                                </label>

                                <input
                                    id="smtp_test_email"
                                    type="email"
                                    class="w-full rounded-md border px-3 py-2.5 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                    :placeholder="`@lang('admin::app.configuration.index.email.smtp.test.recipient-placeholder')`"
                                    v-model="testEmail"
                                    @keyup.enter="submit"
                                />
                            </div>
                        </x-slot>

                        <x-slot:footer>
                            <div class="flex items-center gap-2.5">
                                <button
                                    type="button"
                                    class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800"
                                    @click="$refs.smtpTestModal.close()"
                                >
                                    @lang('admin::app.configuration.index.back')
                                </button>

                                <button
                                    type="button"
                                    class="primary-button"
                                    :disabled="isLoading"
                                    @click="submit"
                                >
                                    <span v-if="isLoading">
                                        @lang('admin::app.configuration.index.email.smtp.test.testing-btn')
                                    </span>

                                    <span v-else>
                                        @lang('admin::app.configuration.index.email.smtp.test.send-btn')
                                    </span>
                                </button>
                            </div>
                        </x-slot>
                    </x-admin::modal>
                </div>
            </script>

            <script type="module">
                app.component('v-smtp-configuration-test', {
                    template: '#v-smtp-configuration-test-template',

                    data() {
                        return {
                            isLoading: false,
                            testEmail: '',
                        };
                    },

                    methods: {
                        async submit() {
                            if (! this.testEmail) {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: "@lang('admin::app.configuration.index.email.smtp.test.validation-error')",
                                });

                                return;
                            }

                            this.isLoading = true;

                            try {
                                const form = this.$el.closest('form');
                                const formData = new FormData(form);

                                formData.append('test_email', this.testEmail);

                                const response = await this.$axios.post(
                                    "{{ route('admin.configuration.smtp.test') }}",
                                    formData
                                );

                                this.$emitter.emit('add-flash', {
                                    type: 'success',
                                    message: response.data.message,
                                });

                                this.$refs.smtpTestModal.close();
                            } catch (error) {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error?.response?.data?.message || "@lang('admin::app.configuration.index.email.smtp.test.errors.generic')",
                                });
                            } finally {
                                this.isLoading = false;
                            }
                        },
                    },
                });
            </script>
        @endPushOnce
    @endif

    @if ($activeConfiguration?->getKey() === 'whatsapp')
        @pushOnce('scripts')
            <script
                type="text/x-template"
                id="v-whatsapp-test-message-template"
            >
                <div>
                    <div @click="$refs.whatsappTestMessageModal.open()">
                        <slot></slot>
                    </div>

                    <x-admin::modal ref="whatsappTestMessageModal">
                        <x-slot:header>
                            <p class="text-lg font-semibold text-gray-800 dark:text-white">
                                @lang('admin::app.configuration.index.whatsapp.test-message.modal-title')
                            </p>
                        </x-slot>

                        <x-slot:content>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                @lang('admin::app.configuration.index.whatsapp.test-message.modal-info')
                            </p>

                            <div class="mt-4">
                                <label
                                    for="whatsapp_test_phone"
                                    class="required mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
                                >
                                    @lang('admin::app.configuration.index.whatsapp.test-message.phone')
                                </label>

                                <input
                                    id="whatsapp_test_phone"
                                    type="text"
                                    class="w-full rounded-md border px-3 py-2.5 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                    :placeholder="`@lang('admin::app.configuration.index.whatsapp.test-message.phone-placeholder')`"
                                    v-model="phone"
                                />
                            </div>

                            <div class="mt-4">
                                <label
                                    for="whatsapp_test_message"
                                    class="required mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
                                >
                                    @lang('admin::app.configuration.index.whatsapp.test-message.message')
                                </label>

                                <textarea
                                    id="whatsapp_test_message"
                                    class="w-full rounded-md border px-3 py-2.5 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                    rows="4"
                                    :placeholder="`@lang('admin::app.configuration.index.whatsapp.test-message.message-placeholder')`"
                                    v-model="message"
                                ></textarea>
                            </div>
                        </x-slot>

                        <x-slot:footer>
                            <div class="flex items-center gap-2.5">
                                <button
                                    type="button"
                                    class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800"
                                    @click="$refs.whatsappTestMessageModal.close()"
                                >
                                    @lang('admin::app.configuration.index.back')
                                </button>

                                <button
                                    type="button"
                                    class="primary-button"
                                    :disabled="isLoading"
                                    @click="submit"
                                >
                                    <span v-if="isLoading">
                                        @lang('admin::app.configuration.index.whatsapp.test-message.sending-btn')
                                    </span>

                                    <span v-else>
                                        @lang('admin::app.configuration.index.whatsapp.test-message.send-btn')
                                    </span>
                                </button>
                            </div>
                        </x-slot>
                    </x-admin::modal>
                </div>
            </script>

            <script type="module">
                app.component('v-whatsapp-configuration-test', {
                    template: `
                        <div @click="submit">
                            <slot></slot>
                        </div>
                    `,

                    data() {
                        return {
                            isLoading: false,
                        };
                    },

                    methods: {
                        async submit() {
                            if (this.isLoading) {
                                return;
                            }

                            this.isLoading = true;

                            try {
                                const form = this.$el.closest('form');
                                const formData = new FormData(form);

                                const response = await this.$axios.post(
                                    "{{ route('admin.configuration.whatsapp.test') }}",
                                    formData
                                );

                                this.$emitter.emit('add-flash', {
                                    type: 'success',
                                    message: response.data.message,
                                });
                            } catch (error) {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error?.response?.data?.message || "@lang('admin::app.configuration.index.whatsapp.test.errors.generic')",
                                });
                            } finally {
                                this.isLoading = false;
                            }
                        },
                    },
                });

                app.component('v-whatsapp-test-message', {
                    template: '#v-whatsapp-test-message-template',

                    data() {
                        return {
                            isLoading: false,
                            phone: '',
                            message: '',
                        };
                    },

                    methods: {
                        async submit() {
                            if (! this.phone || ! this.message) {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: "@lang('admin::app.configuration.index.whatsapp.test-message.validation-error')",
                                });

                                return;
                            }

                            this.isLoading = true;

                            try {
                                const form = this.$el.closest('form');
                                const formData = new FormData(form);

                                formData.append('phone', this.phone);
                                formData.append('message', this.message);

                                const response = await this.$axios.post(
                                    "{{ route('admin.configuration.whatsapp.test_message') }}",
                                    formData
                                );

                                this.$emitter.emit('add-flash', {
                                    type: 'success',
                                    message: response.data.message,
                                });

                                this.$refs.whatsappTestMessageModal.close();
                            } catch (error) {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error?.response?.data?.message || "@lang('admin::app.configuration.index.whatsapp.test-message.errors.generic')",
                                });
                            } finally {
                                this.isLoading = false;
                            }
                        },
                    },
                });
            </script>
        @endPushOnce
    @endif
</x-admin::layouts>
