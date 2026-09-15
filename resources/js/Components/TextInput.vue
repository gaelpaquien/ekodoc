<script setup>
import { ref } from 'vue';

// Single source of truth for the app's text-input styling — border-radius,
// padding, height, border color, focus treatment. Every real text field in
// the app (Search, Configuration, Editor, TagSelector's combobox) renders
// through this component instead of hand-copying the same Tailwind classes
// per file, which is exactly how they drifted apart (px-3 vs px-4,
// rounded-sm vs rounded-md — code review feedback after
// spec-corrections-documents-ui shipped).
//
// `focus:` rather than `focus-visible:` is deliberate: `:focus-visible`
// only matches keyboard-driven focus in most browsers, so clicking into a
// field with a mouse showed no lime border at all — a real data-entry
// field needs to react to any focus, not only Tab (code review feedback).
//
// `focus:outline-none` is just as deliberate: nothing here ever set
// `outline-none`, so the browser's own native focus ring (its color/shape
// is OS/browser-controlled, not ours — often a light/white halo in dark
// mode) kept rendering on top of/alongside the lime border and visually
// drowned it out. The lime border is the only focus indicator now.
const model = defineModel({ type: [String, Number], default: '' });

const props = defineProps({
    type: {
        type: String,
        default: 'text',
    },
    // Text size/weight is a prop, not something a caller appends via
    // `class` — two Tailwind classes for the same property (e.g. `text-sm`
    // from the base style below and a caller's `text-lg`) don't reliably
    // override by DOM class order, only by Tailwind's own generated
    // stylesheet order. A prop sidesteps that entirely (Editor.vue's title
    // field needs `text-lg font-semibold` instead of the default `text-sm`).
    size: {
        type: String,
        default: 'text-sm',
    },
});

const inputEl = ref(null);

// Every current caller keeps a template ref on this field to call
// `.focus()` (and, when renaming a tag, `.select()`) on it programmatically
// — `ref="x"` on a component only exposes what it `defineExpose`s, so both
// calls must be forwarded explicitly to the real `<input>` underneath.
defineExpose({
    focus: () => inputEl.value?.focus(),
    select: () => inputEl.value?.select(),
});
</script>

<template>
    <input
        ref="inputEl"
        v-model="model"
        :type="props.type"
        :class="[props.size, 'w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:border-primary focus:outline-none disabled:cursor-not-allowed disabled:opacity-50']"
    >
</template>
