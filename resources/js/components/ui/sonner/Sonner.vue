<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import { Toaster as Sonner } from "vue-sonner"

const props = defineProps<ToasterProps>()
</script>

<template>
  <Sonner
    v-bind="props"
    position="bottom-right"
    :style="{
      '--normal-bg': 'var(--popover)',
      '--normal-text': 'var(--popover-foreground)',
      '--normal-border': 'var(--border)',
      '--success-bg': 'var(--popover)',
      '--success-text': 'var(--popover-foreground)',
      '--success-border': 'var(--border)',
      '--error-bg': 'var(--popover)',
      '--error-text': 'var(--popover-foreground)',
      '--error-border': 'var(--border)',
      '--warning-bg': 'var(--popover)',
      '--warning-text': 'var(--popover-foreground)',
      '--warning-border': 'var(--border)',
      '--info-bg': 'var(--popover)',
      '--info-text': 'var(--popover-foreground)',
      '--info-border': 'var(--border)',
    }"
  />
</template>

<style>
[data-sonner-toaster] [data-sonner-toast] {
  border-radius: 12px;
  box-shadow: var(--shadow-lg);
  font-size: 14px;
}

@media (prefers-reduced-motion: no-preference) {
  [data-sonner-toaster] [data-sonner-toast] {
    transition:
      transform var(--motion-duration-toast-enter) var(--motion-easing-overlay-enter),
      opacity var(--motion-duration-toast-enter) var(--motion-easing-overlay-enter),
      height var(--motion-duration-toast-enter) var(--motion-easing-overlay-enter),
      box-shadow 200ms;
  }

  [data-sonner-toaster] [data-sonner-toast][data-removed="true"],
  [data-sonner-toaster] [data-sonner-toast][data-removed="true"][data-front="false"][data-swipe-out="false"][data-expanded="false"] {
    transition:
      transform var(--motion-duration-toast-exit) var(--motion-easing-overlay-exit),
      opacity var(--motion-duration-toast-exit) var(--motion-easing-overlay-exit),
      height var(--motion-duration-toast-exit) var(--motion-easing-overlay-exit);
  }
}

[data-sonner-toast][data-type="success"] [data-icon] {
  color: var(--success) !important;
}

[data-sonner-toast][data-type="error"] [data-icon] {
  color: var(--error) !important;
}

[data-sonner-toast][data-type="warning"] [data-icon] {
  color: var(--warning) !important;
}

[data-sonner-toast][data-type="info"] [data-icon] {
  color: var(--info) !important;
}
</style>
