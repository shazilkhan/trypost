<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        :title="$t('auth.forgot_password.title')"
        :description="$t('auth.forgot_password.description')"
    >
        <Head :title="$t('auth.forgot_password.page_title')" />

        <div
            v-if="status"
            class="rounded-lg bg-success-subtle px-3 py-2 text-center text-sm font-medium text-success-text"
        >
            {{ status }}
        </div>

        <div class="flex flex-col gap-6">
            <Form
                v-bind="email.form()"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-4"
            >
                <div class="grid gap-2">
                    <Label for="email" class="text-base leading-6">{{ $t('auth.forgot_password.email') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="off"
                        autofocus
                        placeholder="email@example.com"
                        class="h-10 rounded-lg px-3 text-base"
                    />
                    <InputError :message="errors.email" />
                </div>

                <Button
                    size="lg"
                    class="w-full text-base"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    {{ $t('auth.forgot_password.submit') }}
                </Button>
            </Form>

            <div class="space-x-1 text-center text-base text-foreground">
                <span>{{ $t('auth.forgot_password.return_to') }}</span>
                <TextLink :href="login()">{{ $t('auth.forgot_password.log_in') }}</TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
