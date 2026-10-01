import { ref } from 'vue';

import dayjs from './dayjs';
import type { Auth } from './types';

export type Theme = 'light' | 'dark' | 'system';
export type TimeFormat = '12h' | '24h';
export type WeekStart = 'sunday' | 'monday';
export type DefaultPostAction = 'next' | 'now' | 'top' | 'custom';

interface PreferenceProps {
    auth?: Auth;
}

/**
 * The signed-in user's display preferences, readable outside components. `@/date`
 * formats every time through `timeFormat`, and dayjs' `startOf('week')` follows
 * `weekStartsOn`, so neither has to be threaded through props. Guests keep the
 * defaults: a 24-hour clock and weeks that start on Monday.
 */
export const timeFormat = ref<TimeFormat>('24h');
export const weekStartsOn = ref<WeekStart>('monday');

/** 0 for Sunday, 1 for Monday — the index dayjs and Reka calendars expect. */
export const weekStartIndex = (): 0 | 1 =>
    weekStartsOn.value === 'sunday' ? 0 : 1;

/** Weekday numbers (0 = Sunday) in the order the user's week runs. */
export const orderedWeekdays = (): number[] =>
    Array.from({ length: 7 }, (_, index) => (index + weekStartIndex()) % 7);

/**
 * Put the `dark` class on <html> for the theme. "system" is resolved against the
 * OS here too; the listener that follows later OS changes lives in the inline
 * script in app.blade.php and reads `data-theme`, so it needs no second copy.
 */
export const applyTheme = (theme: Theme): void => {
    const root = document.documentElement;
    const dark =
        theme === 'dark' ||
        (theme === 'system' &&
            window.matchMedia('(prefers-color-scheme: dark)').matches);

    root.dataset.theme = theme;
    root.classList.toggle('dark', dark);
};

const alignWeekStart = (): void => {
    const weekStart = weekStartIndex();

    Object.keys(dayjs.Ls).forEach((locale) =>
        dayjs.updateLocale(locale, { weekStart }),
    );
};

export const syncPreferences = (props: PreferenceProps): void => {
    const user = props.auth?.user;

    timeFormat.value = user?.time_format ?? '24h';
    weekStartsOn.value = user?.week_starts_on ?? 'monday';
    alignWeekStart();

    applyTheme(user?.theme ?? 'light');
};
