<script setup>
// One animated stat for the public stats band. The value is a CMS-authored
// string ("12,000+", "98%", "24/7"); the numeric portion counts up from 0 when
// the stat first scrolls into view, keeping any prefix/suffix ("+", "%", …)
// intact. Non-numeric values (or reduced-motion users) render the raw string
// immediately — nothing is ever hidden or delayed for them.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    value: { type: String, default: '' },
    label: { type: String, default: '' },
});

const el = ref(null);
const display = ref(props.value);

// Split "12,000+" into prefix/number/suffix. "24/7" has a suffix of "/7" so it
// still animates sensibly (counts to 24, keeps "/7").
const parsed = computed(() => {
    const match = /^(.*?)([\d][\d,]*(?:\.\d+)?)(.*)$/.exec(props.value ?? '');
    if (!match) return null;
    return {
        prefix: match[1],
        target: Number(match[2].replace(/,/g, '')),
        decimals: (match[2].split('.')[1] ?? '').length,
        grouped: match[2].includes(','),
        suffix: match[3],
    };
});

let observer = null;
let frame = null;

function animate() {
    const { prefix, target, decimals, grouped, suffix } = parsed.value;
    const duration = 1400;
    const start = performance.now();

    const tick = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        // ease-out cubic — fast start, gentle landing on the final value
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = target * eased;
        const formatted = grouped
            ? Math.round(current).toLocaleString('en-AU')
            : current.toFixed(decimals);
        display.value = `${prefix}${formatted}${suffix}`;
        if (progress < 1) frame = requestAnimationFrame(tick);
    };

    frame = requestAnimationFrame(tick);
}

onMounted(() => {
    const reduce =
        typeof IntersectionObserver === 'undefined' ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!parsed.value || reduce) return;

    display.value = `${parsed.value.prefix}0${parsed.value.suffix}`;

    observer = new IntersectionObserver(
        (entries) => {
            if (!entries[0].isIntersecting) return;
            observer.disconnect();
            animate();
        },
        { threshold: 0.4 },
    );
    observer.observe(el.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    if (frame) cancelAnimationFrame(frame);
});
</script>

<template>
    <div ref="el" class="text-center">
        <p class="text-3xl font-bold tracking-tight text-ink-900 tabular-nums dark:text-ink-50 sm:text-4xl">
            {{ display }}
        </p>
        <p class="mt-1.5 text-sm text-ink-500 dark:text-ink-400">{{ label }}</p>
    </div>
</template>
