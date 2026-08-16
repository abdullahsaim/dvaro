// v-reveal — a tiny IntersectionObserver scroll-reveal directive for the
// public marketing pages. Fades + rises an element the first time it scrolls
// into view. Zero dependencies (no AOS/GSAP — the bundle stays lean).
//
// Usage:  <div v-reveal>…</div>
//         <div v-reveal="150">…</div>   ← optional stagger delay in ms
//
// Accessibility: when the user prefers reduced motion (or the browser lacks
// IntersectionObserver), the directive does nothing — content simply renders
// visible with no transform, so nothing is ever hidden from them.
// Transform/opacity only — reveals never cause layout shift.

function reduceMotion() {
    return (
        typeof window === 'undefined' ||
        typeof IntersectionObserver === 'undefined' ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

const EASE = 'cubic-bezier(0.4, 0, 0.2, 1)';

export const vReveal = {
    mounted(el, binding) {
        if (reduceMotion()) return;

        const delay = Number(binding.value) || 0;

        el.style.opacity = '0';
        el.style.transform = 'translateY(18px)';
        el.style.transition = `opacity 0.6s ${EASE} ${delay}ms, transform 0.6s ${EASE} ${delay}ms`;
        el.style.willChange = 'opacity, transform';

        const observer = new IntersectionObserver(
            (entries) => {
                if (!entries[0].isIntersecting) return;
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
                observer.disconnect();
                // Drop the will-change hint once the reveal has played out.
                setTimeout(() => (el.style.willChange = ''), 700 + delay);
            },
            { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
        );

        observer.observe(el);
        el._revealObserver = observer;
    },

    unmounted(el) {
        el._revealObserver?.disconnect();
        delete el._revealObserver;
    },
};
