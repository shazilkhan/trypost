<script setup lang="ts">
import type { DateRange } from "reka-ui"
import type { Ref } from "vue"
import {
  CalendarDate,
  getLocalTimeZone,
} from "@internationalized/date"
import { IconCalendar } from "@tabler/icons-vue"
import { computed, nextTick, ref, watch } from "vue"
import { useWindowSize } from "@vueuse/core"
import { Button } from "@/components/ui/button"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { RangeCalendar } from "@/components/ui/range-calendar"
import { useCalendarLocale } from "@/composables/useCalendarLocale"
import { cn } from "@/lib/utils"
import date from "@/date"

const props = defineProps<{
  modelValue: { start: Date, end: Date }
  triggerClass?: string
  minDate?: Date
  maxDate?: Date
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: { start: Date, end: Date }]
}>()

const calendarLocale = useCalendarLocale()

const toCalendarDate = (dateValue: Date) => {
  return new CalendarDate(
    dateValue.getFullYear(),
    dateValue.getMonth() + 1,
    dateValue.getDate(),
  )
}

const toDate = (calendarDate: any) => {
  if (!calendarDate) return new Date()
  return calendarDate.toDate(getLocalTimeZone())
}

const value = ref({
  start: toCalendarDate(props.modelValue.start),
  end: toCalendarDate(props.modelValue.end),
}) as Ref<DateRange>

const isUpdating = ref(false)
const isOpen = defineModel<boolean>("open", { default: false })
const { width } = useWindowSize()
const numberOfMonths = computed(() => width.value < 640 ? 1 : 2)
const minimum = computed(() => props.minDate ? toCalendarDate(props.minDate) : undefined)
const maximum = computed(() => props.maxDate ? toCalendarDate(props.maxDate) : undefined)
watch(
  () => props.modelValue,
  (newVal) => {
    if (!isUpdating.value) {
      value.value = {
        start: toCalendarDate(newVal.start),
        end: toCalendarDate(newVal.end),
      }
    }
  },
  { deep: true },
)

watch(
  value,
  (newVal) => {
    if (newVal.start && newVal.end) {
      isUpdating.value = true
      emit("update:modelValue", {
        start: toDate(newVal.start),
        end: toDate(newVal.end),
      })
      nextTick(() => {
        isUpdating.value = false
      })
    }
  },
  { deep: true },
)
</script>

<template>
  <Popover v-model:open="isOpen">
    <PopoverTrigger as-child>
      <slot name="trigger">
        <Button
          variant="outline"
          data-testid="date-range-picker-trigger"
          :disabled="disabled"
          :class="cn(
            'w-full justify-start text-left font-medium sm:w-auto',
            !value && 'text-foreground/60',
            props.triggerClass,
          )"
        >
          <template v-if="value.start">
            <template v-if="value.end">
              {{ date.formatLocalDate(toDate(value.start)) }} -
              {{ date.formatLocalDate(toDate(value.end)) }}
            </template>
            <template v-else>
              {{ date.formatLocalDate(toDate(value.start)) }}
            </template>
          </template>
          <template v-else>
            {{ $t('common.date_range_picker.placeholder') }}
          </template>
          <IconCalendar class="ml-auto size-4 text-foreground/60" />
        </Button>
      </slot>
    </PopoverTrigger>
    <PopoverContent class="w-auto p-0" align="end">
      <div class="flex flex-col sm:flex-row">
        <div class="shrink-0">
          <RangeCalendar
            v-model="value"
            initial-focus
            :locale="calendarLocale"
            :number-of-months="numberOfMonths"
            :min-value="minimum"
            :max-value="maximum"
          />
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>
