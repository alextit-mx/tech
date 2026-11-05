<script setup lang="ts">
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import CustomerFields from '@/components/CustomerFields.vue';
import { emptyCustomer } from '@/lib/customers';
import type {
    CustomerData,
    PickedCustomer,
    SatCatalogs,
} from '@/lib/customers';
import { store } from '@/routes/admin/customers';
import { firstError, http } from '@/lib/http';

const open = defineModel<boolean>({ required: true });
const props = defineProps<{ initialName?: string }>();
const emit = defineEmits<{ saved: [customer: PickedCustomer] }>();
const page = usePage<{ sat: SatCatalogs }>();
const form = ref(emptyCustomer());
const errors = ref<Partial<Record<keyof CustomerData, string>>>({});
const message = ref('');
const saving = ref(false);
watch(open, (value) => {
    if (value) {
        form.value = { ...emptyCustomer(), name: props.initialName ?? '' };
        errors.value = {};
        message.value = '';
    }
});
async function save() {
    if (saving.value) return;
    saving.value = true;
    errors.value = {};
    message.value = '';
    const result = await http<
        PickedCustomer & { errors?: Record<string, string[]> }
    >('POST', store.url(), form.value);
    saving.value = false;
    if (!result.ok) {
        errors.value = Object.fromEntries(
            Object.entries(result.data?.errors ?? {}).map(([key, value]) => [
                key,
                value[0],
            ]),
        );
        message.value = firstError(result.data);
        return;
    }
    emit('saved', result.data);
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[90dvh] overflow-y-auto sm:max-w-3xl"
            @interact-outside="saving && $event.preventDefault()"
        >
            <DialogHeader>
                <DialogTitle>Nuevo cliente</DialogTitle>
                <DialogDescription
                    >Guarda el contacto para seleccionarlo en este
                    documento.</DialogDescription
                >
            </DialogHeader>
            <form class="space-y-5" @submit.prevent="save">
                <CustomerFields
                    v-model="form"
                    :sat="page.props.sat"
                    :errors="errors"
                />
                <p v-if="message" role="alert" class="text-sm text-destructive">
                    {{ message }}
                </p>
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="saving"
                        @click="open = false"
                        >Cancelar</Button
                    >
                    <Button :disabled="saving">{{
                        saving ? 'Guardando…' : 'Guardar cliente'
                    }}</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
