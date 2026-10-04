<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <div
    data-slot="dialog-footer"
    :class="cn('-mx-4 -mb-2 flex flex-col gap-2 rounded-xl bg-muted p-3 sm:flex-row sm:items-center sm:justify-end [&_[data-variant=ghost]:hover]:bg-secondary', props.class)"
  >
    <slot />
  </div>
</template>
