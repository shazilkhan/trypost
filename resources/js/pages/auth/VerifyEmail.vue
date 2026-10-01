<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        :title="$t('auth.verify_email.title')"
        :description="$t('auth.verify_email.description')"
    >
        <Head :title="$t('auth.verify_email.page_title')" />

        <div
            v-if="status === 'verification-link-sent'"
            class="rounded-lg bg-success-subtle px-3 py-2 text-center text-sm font-medium text-success-text"
        >
            {{ $t('auth.verify_email.link_sent') }}
        </div>

        <Form
            v-bind="send.form()"
            class="flex flex-col items-center gap-4 text-center"
            v-slot="{ processing }"
        >
            <Button :disabled="processing" size="lg" class="w-full text-base">
                <Spinner v-if="processing" />
                {{ $t('auth.verify_email.resend') }}
            </Button>

            <TextLink
                :href="logout()"
                as="button"
                class="text-base"
            >
                {{ $t('auth.verify_email.log_out') }}
            </TextLink>
        </Form>
    </AuthLayout>
</template>
