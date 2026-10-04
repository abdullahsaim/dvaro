<script setup>
// Shared HTML5 canvas signature pad — mouse + touch, base64 PNG data URL
// output. Used by the staff in-person signing flow (Agreement/Show.vue) and
// the public remote review-and-sign page, so the drawing logic lives in one
// place rather than being copy-pasted between an authenticated and an
// unauthenticated page.
import { ref } from 'vue';

const emit = defineEmits(['change']);

const canvas = ref(null);
const hasDrawn = ref(false);
let drawing = false;

function pos(event) {
    const rect = canvas.value.getBoundingClientRect();
    const point = event.touches ? event.touches[0] : event;
    return { x: point.clientX - rect.left, y: point.clientY - rect.top };
}

function start(event) {
    event.preventDefault();
    drawing = true;
    const ctx = canvas.value.getContext('2d');
    const { x, y } = pos(event);
    ctx.beginPath();
    ctx.moveTo(x, y);
}

function move(event) {
    if (!drawing) return;
    event.preventDefault();
    const ctx = canvas.value.getContext('2d');
    const { x, y } = pos(event);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#0f172a';
    ctx.lineTo(x, y);
    ctx.stroke();
    hasDrawn.value = true;
    emit('change', hasDrawn.value);
}

function stop() {
    drawing = false;
}

function clearPad() {
    const ctx = canvas.value.getContext('2d');
    ctx.clearRect(0, 0, canvas.value.width, canvas.value.height);
    hasDrawn.value = false;
    emit('change', false);
}

/** The drawn signature as a PNG data URL, or null if nothing was drawn. */
function dataUrl() {
    return hasDrawn.value ? canvas.value.toDataURL('image/png') : null;
}

defineExpose({ clearPad, dataUrl, hasDrawn });
</script>

<template>
    <canvas
        ref="canvas"
        width="480"
        height="180"
        class="w-full max-w-[480px] touch-none rounded-control border border-ink-300 bg-white dark:border-ink-700"
        @mousedown="start"
        @mousemove="move"
        @mouseup="stop"
        @mouseleave="stop"
        @touchstart="start"
        @touchmove="move"
        @touchend="stop"
    ></canvas>
</template>
