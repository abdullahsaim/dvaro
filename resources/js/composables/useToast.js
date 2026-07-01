// Toast notifications — a tiny singleton store shared across the app. Layouts
// mount one <Toast /> which both renders this stack and feeds Inertia flash
// messages into it. Auto-dismiss is handled per toast (default 4s).
import { ref } from 'vue';

const toasts = ref([]);
let seq = 0;

function remove(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

function push(message, type = 'info', timeout = 4000) {
    if (!message) return null;
    const id = ++seq;
    toasts.value.push({ id, message, type });
    if (timeout) {
        setTimeout(() => remove(id), timeout);
    }
    return id;
}

export function useToast() {
    return {
        toasts,
        remove,
        success: (m, t) => push(m, 'success', t),
        error: (m, t) => push(m, 'error', t),
        info: (m, t) => push(m, 'info', t),
    };
}
