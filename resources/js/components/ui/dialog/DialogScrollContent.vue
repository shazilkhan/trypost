<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { IconX } from "@tabler/icons-vue"
import {
  DialogClose,
  DialogContent,
  DialogOverlay,
  DialogPortal,
  useForwardPropsEmits,
} from "reka-ui"
import { cn } from "@/lib/utils"

defineOptions({
  inheritAttrs: false,
})

const props = defineProps<DialogContentProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<DialogContentEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <DialogPortal>
    <DialogOverlay
      class="motion-dialog-overlay fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/50"
    >
      <DialogContent
        :class="
          cn(
            'motion-dialog relative z-50 grid w-full max-w-[calc(100%-2rem)] my-8 gap-4 rounded-2xl bg-background px-6 pt-6 pb-4 shadow-lg duration-200 sm:max-w-lg md:w-full dark:border',
            props.class,
          )
        "
        v-bind="{ ...$attrs, ...forwarded }"
        @pointer-down-outside="(event) => {
          const originalEvent = event.detail.originalEvent;
          const target = originalEvent.target as HTMLElement;
          if (originalEvent.offsetX > target.clientWidth || originalEvent.offsetY > target.clientHeight) {
            event.preventDefault();
          }
        }"
      >
        <slot />

        <DialogClose
          data-slot="dialog-close"
          data-testid="dialog-close"
          class="absolute top-4 right-4 inline-flex size-8 cursor-pointer items-center justify-center rounded-lg border border-border-strong bg-card text-foreground transition-control hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring sm:right-6 [&_svg]:pointer-events-none [&_svg]:shrink-0"
        >
          <IconX class="size-4" />
          <span class="sr-only">{{ $t('common.close') }}</span>
        </DialogClose>
      </DialogContent>
    </DialogOverlay>
  </DialogPortal>
</template>
