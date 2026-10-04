<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import ConnectPopupHeader from '@/components/channels/ConnectPopupHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PopupLayout from '@/layouts/PopupLayout.vue';
import { authorize as authorizeMastodon } from '@/routes/app/social/mastodon';

const form = useForm({ instance: 'https://mastodon.social' });

const onSubmit = (): void => {
    form.post(authorizeMastodon.url());
};
</script>

<template>
    <PopupLayout :title="$t('accounts.mastodon.title')">
        <div class="mx-auto flex max-w-md flex-col gap-8 pt-4">
            <ConnectPopupHeader
                platform="mastodon"
                :title="$t('accounts.mastodon.title')"
                :description="$t('accounts.mastodon.description')"
            />

            <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
                <div class="grid gap-2">
                    <Label for="instance">{{ $t('accounts.mastodon.instance_url') }}</Label>
                    <Input
                        id="instance"
                        v-model="form.instance"
                        type="url"
                        autofocus
                        :placeholder="$t('accounts.mastodon.instance_placeholder')"
                        :aria-invalid="Boolean(form.errors.instance)"
                        data-testid="mastodon-instance"
                    />
                    <InputError :message="form.errors.instance" />
                    <p class="text-sm text-muted-foreground" data-testid="mastodon-instance-hint">
                        {{ $t('accounts.mastodon.instance_hint') }}
                    </p>
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    :disabled="form.processing"
                    data-testid="mastodon-submit"
                >
                    {{
                        form.processing
                            ? $t('accounts.mastodon.submitting')
                            : $t('accounts.mastodon.submit')
                    }}
                </Button>
            </form>
        </div>
    </PopupLayout>
</template>
