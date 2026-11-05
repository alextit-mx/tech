<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { lookup } from '@/routes/admin/customers';
import CustomerDialog from '@/components/CustomerDialog.vue';
import { customerAddress } from '@/lib/customers';
import type { PickedCustomer } from '@/lib/customers';
export type { PickedCustomer } from '@/lib/customers';

const model = defineModel<PickedCustomer | null>({ required: true });
defineProps<{ error?: string }>();
const query = ref('');
const results = ref<PickedCustomer[]>([]);
const creating = ref(false);
const searchError = ref('');
const loading = ref(false);
let timer: number | undefined;
let controller: AbortController | undefined;
onBeforeUnmount(() => {
    window.clearTimeout(timer);
    controller?.abort();
});

function search() {
    window.clearTimeout(timer);
    controller?.abort();
    results.value = [];
    searchError.value = '';
    loading.value = false;
    if (query.value.trim().length < 2) return;
    timer = window.setTimeout(async () => {
        controller = new AbortController();
        const request = controller;
        loading.value = true;
        try {
            const response = await fetch(
                lookup.url({ query: { q: query.value } }),
                {
                    headers: { Accept: 'application/json' },
                    signal: request.signal,
                },
            );
            if (!response.ok) throw new Error();
            results.value = await response.json();
            if (!results.value.length)
                searchError.value = 'No se encontraron clientes.';
        } catch {
            if (!request.signal.aborted)
                searchError.value = 'No se pudo buscar. Intenta de nuevo.';
        } finally {
            if (!request.signal.aborted) loading.value = false;
        }
    }, 220);
}
function choose(customer: PickedCustomer) {
    controller?.abort();
    window.clearTimeout(timer);
    model.value = customer;
    results.value = [];
    query.value = '';
    searchError.value = '';
}
</script>

<template>
    <div class="space-y-2">
        <div v-if="model" class="space-y-2 rounded-md border p-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-semibold">
                        {{ model.company || model.legal_name || model.name }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ model.name
                        }}{{ model.phone ? ` · ${model.phone}` : '' }}
                    </p>
                </div>
                <Button
                    variant="ghost"
                    type="button"
                    size="sm"
                    @click="model = null"
                    >Cambiar</Button
                >
            </div>
            <dl
                v-if="model.kind === 'registrado'"
                class="space-y-1 border-t pt-2 text-sm"
            >
                <div>
                    <dt class="inline text-muted-foreground">RFC:</dt>
                    <dd class="inline">{{ model.rfc }}</dd>
                </div>
                <div>
                    <dt class="inline text-muted-foreground">Razón social:</dt>
                    <dd class="inline">{{ model.legal_name }}</dd>
                </div>
                <div>
                    <dt class="inline text-muted-foreground">Dirección:</dt>
                    <dd class="inline">
                        {{
                            customerAddress(model) || 'Sin dirección registrada'
                        }}
                    </dd>
                </div>
            </dl>
        </div>
        <template v-else>
            <div class="relative">
                <Input
                    v-model="query"
                    aria-label="Buscar cliente por empresa, nombre, RFC o WhatsApp"
                    placeholder="Empresa, nombre, RFC o WhatsApp"
                    @input="search"
                />
                <ul
                    v-if="results.length"
                    class="absolute z-10 mt-1 max-h-80 w-full overflow-y-auto rounded-md border bg-background shadow"
                >
                    <li v-for="customer in results" :key="customer.id">
                        <Button
                            variant="ghost"
                            type="button"
                            class="h-auto min-h-12 w-full flex-col items-start px-3 py-2 text-left whitespace-normal"
                            @click="choose(customer)"
                        >
                            <span class="font-semibold">{{
                                customer.company ||
                                customer.legal_name ||
                                customer.name
                            }}</span>
                            <span class="text-xs text-muted-foreground"
                                >{{ customer.name }} ·
                                {{ customer.rfc || customer.phone }}</span
                            >
                        </Button>
                    </li>
                </ul>
            </div>
            <p
                v-if="loading"
                role="status"
                class="text-sm text-muted-foreground"
            >
                Buscando…
            </p>
            <p
                v-else-if="searchError"
                role="status"
                class="text-sm text-muted-foreground"
            >
                {{ searchError }}
            </p>
            <Button
                variant="ghost"
                type="button"
                size="sm"
                @click="creating = true"
                >+ Nuevo cliente</Button
            >
        </template>
        <CustomerDialog
            v-model="creating"
            :initial-name="query"
            @saved="choose"
        />
        <p v-if="error" role="alert" class="text-sm text-destructive">
            {{ error }}
        </p>
    </div>
</template>
