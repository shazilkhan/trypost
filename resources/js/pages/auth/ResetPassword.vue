<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password';

const props = defineProps<{
    token: string;
    email: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <AuthLayout
        :title="$t('auth.reset_password.title')"
        :description="$t('auth.reset_password.description')"
    >
        <Head :title="$t('auth.reset_password.page_title')" />

        <Form
            v-bind="store.form()"
            :transform="(data) => ({ ...data, token, email })"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="email" class="text-base leading-6">{{ $t('auth.reset_password.email') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        v-model="inputEmail"
                        class="h-10 rounded-lg bg-muted px-3 text-base text-muted-foreground"
                        readonly
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password" class="text-base leading-6">{{ $t('auth.reset_password.password') }}</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        class="h-10 rounded-lg px-3 text-base"
                        autofocus
                        :placeholder="$t('auth.reset_password.password')"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation" class="text-base leading-6">
                        {{ $t('auth.reset_password.confirm_password') }}
                    </Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        class="h-10 rounded-lg px-3 text-base"
                        :placeholder="$t('auth.reset_password.confirm_placeholder')"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    size="lg"
                    class="w-full text-base"
                    :disabled="processing"
                    data-test="reset-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ $t('auth.reset_password.submit') }}
                </Button>
            </div>
        </Form>
    </AuthLayout>
</template>
