@php
    use Webkul\Commercial\Enums\AccountProductStatus;
    $statuses = AccountProductStatus::cases();
    $users = app(\Webkul\User\Repositories\UserRepository::class)->all();
    $cancelAlpine = $cancelAlpine ?? null;
@endphp

<form
    id="{{ $formId }}"
    class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900"
    @submit.prevent="window.submitAccountProduct('{{ $formId }}')"
>
    <input type="hidden" name="entity_type" value="{{ $entityType }}">
    <input type="hidden" name="entity_id" value="{{ $entityId }}">

    {{-- Produto --}}
    <div class="flex flex-col gap-1">
        <label class="label required dark:text-white text-sm">Produto SaaS</label>
        <select
            name="crm_product_id"
            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            required
        >
            <option value="">Selecione…</option>
            @foreach ($crmProducts as $product)
                <option
                    value="{{ $product->id }}"
                    @if ($existingLink && $existingLink->crm_product_id == $product->id) selected @endif
                >
                    {{ $product->name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Status --}}
    <div class="flex flex-col gap-1">
        <label class="label required dark:text-white text-sm">Status Comercial</label>
        <select
            name="status"
            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            required
        >
            <option value="">Selecione…</option>
            @foreach ($statuses as $status)
                <option
                    value="{{ $status->value }}"
                    @if ($existingLink && $existingLink->status->value === $status->value) selected @endif
                >
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Responsável comercial --}}
    <div class="flex flex-col gap-1">
        <label class="label dark:text-white text-sm">Responsável</label>
        <select
            name="user_id"
            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
        >
            <option value="">Nenhum</option>
            @foreach ($users as $user)
                <option
                    value="{{ $user->id }}"
                    @if ($existingLink && $existingLink->user_id == $user->id) selected @endif
                >
                    {{ $user->name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Notas --}}
    <div class="flex flex-col gap-1">
        <label class="label dark:text-white text-sm">Notas</label>
        <textarea
            name="notes"
            rows="2"
            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
        >{{ $existingLink?->notes }}</textarea>
    </div>

    <div class="flex justify-end gap-2">
        @if ($cancelAlpine)
            <button
                type="button"
                class="secondary-button text-sm"
                @click="{{ $cancelAlpine }}"
            >
                Cancelar
            </button>
        @endif

        <button
            type="submit"
            class="primary-button text-sm"
        >
            {{ $submitLabel }}
        </button>
    </div>
</form>

@pushOnce('scripts')
<script type="module">
if (! window.__accountProductFormInit) {
    window.__accountProductFormInit = true;

    window.submitAccountProduct = async function(formId) {
        const form = document.getElementById(formId);
        if (! form) return;

        const formData = new FormData(form);
        const body = {};
        formData.forEach((value, key) => { body[key] = value; });

        try {
            const response = await fetch('{{ route('admin.contacts.account_products.store') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(body),
            });

            const data = await response.json();

            if (response.ok) {
                window.dispatchEvent(new CustomEvent('krayin-notification', {
                    detail: { type: 'success', message: data.message }
                }));
                setTimeout(() => window.location.reload(), 600);
            } else {
                const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : data.message;
                window.dispatchEvent(new CustomEvent('krayin-notification', {
                    detail: { type: 'error', message: firstError }
                }));
            }
        } catch (e) {
            window.dispatchEvent(new CustomEvent('krayin-notification', {
                detail: { type: 'error', message: 'Erro de conexão.' }
            }));
        }
    };

    window.deleteAccountProduct = async function(id) {
        if (! confirm('Remover este vínculo comercial?')) return;

        try {
            const response = await fetch(`{{ rtrim(url('admin/contacts/account-products'), '/') }}/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();

            if (response.ok) {
                window.dispatchEvent(new CustomEvent('krayin-notification', {
                    detail: { type: 'success', message: data.message }
                }));
                setTimeout(() => window.location.reload(), 600);
            } else {
                window.dispatchEvent(new CustomEvent('krayin-notification', {
                    detail: { type: 'error', message: data.message }
                }));
            }
        } catch (e) {
            window.dispatchEvent(new CustomEvent('krayin-notification', {
                detail: { type: 'error', message: 'Erro de conexão.' }
            }));
        }
    };
}
</script>
@endPushOnce
