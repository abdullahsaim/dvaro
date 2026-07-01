// Sidebar UI state shared between the layout, Sidebar and TopBar. Desktop
// collapse is a simple UI preference persisted to localStorage (NOT server-side
// per spec). Collapsed-by-default (explicit user toggle reveals labels — not
// hover). Mobile drawer open/close is ephemeral (not persisted).
import { ref } from 'vue';

const STORAGE_KEY = 'sidebar-collapsed';

function readCollapsed() {
    try {
        // Default collapsed (null or '1'); only an explicit '0' expands.
        return localStorage.getItem(STORAGE_KEY) !== '0';
    } catch (e) {
        return true;
    }
}

// Module-level singletons so every consumer stays in sync.
const collapsed = ref(readCollapsed());
const mobileOpen = ref(false);

function toggleCollapsed() {
    collapsed.value = !collapsed.value;
    try {
        localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0');
    } catch (e) { /* ignore */ }
}

function openMobile() {
    mobileOpen.value = true;
}

function closeMobile() {
    mobileOpen.value = false;
}

export function useSidebar() {
    return { collapsed, mobileOpen, toggleCollapsed, openMobile, closeMobile };
}
