<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import ConnectPopupHeader from '@/components/channels/ConnectPopupHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PopupLayout from '@/layouts/PopupLayout.vue';
import { store as storeBluesky } from '@/routes/app/social/bluesky';

const form = useForm({ identifier: '', password: '' });

const onSubmit = (): void => {
    form.post(storeBluesky.url());
};
</script>

<template>
    <PopupLayout :title="$t('accounts.bluesky.title')">
        <div class="mx-auto flex max-w-md flex-col gap-8 pt-4">
            <ConnectPopupHeader
                platform="bluesky"
                :title="$t('accounts.bluesky.title')"
                :description="$t('accounts.bluesky.description')"
            />

            <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
                <div class="grid gap-2">
                    <Label for="identifier">{{ $t('accounts.bluesky.email') }}</Label>
                    <Input
                        id="identifier"
                        v-model="form.identifier"
                        type="text"
                        autocomplete="username"
                        autofocus
                        :placeholder="$t('accounts.bluesky.email_placeholder')"
                        :aria-invalid="Boolean(form.errors.identifier)"
                        data-testid="bluesky-identifier"
                    />
                    <InputError :message="form.errors.identifier" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ $t('accounts.bluesky.app_password') }}</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="off"
                        :placeholder="$t('accounts.bluesky.app_password_placeholder')"
                        :aria-invalid="Boolean(form.errors.password)"
                        data-testid="bluesky-password"
                    />
                    <InputError :message="form.errors.password" />
                    <p
                        class="text-sm text-muted-foreground [&_a]:text-foreground [&_a]:underline [&_a]:underline-offset-4"
                        data-testid="bluesky-app-password-hint"
                        v-html="$t('accounts.bluesky.app_password_hint')"
                    />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    :disabled="form.processing"
                    data-testid="bluesky-submit"
                >
                    {{
                        form.processing
                            ? $t('accounts.bluesky.submitting')
                            : $t('accounts.bluesky.submit')
                    }}
                </Button>
            </form>
        </div>
    </PopupLayout>
</template>
