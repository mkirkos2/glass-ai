<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
        fill="none"
        aria-label="Glass AI"
        role="img"
    >
        <path
            d="M20 4L23.7 15.8L36 20L23.7 24.2L20 36L16.3 24.2L4 20L16.3 15.8L20 4Z"
            fill="currentColor"
        />
        <circle cx="31" cy="8" r="3" fill="#B9A7FF" />
    </svg>
</template>
