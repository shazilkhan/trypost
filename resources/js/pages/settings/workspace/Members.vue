<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconPlus } from '@tabler/icons-vue';
import { ref } from 'vue';

import UsersTab from '@/components/settings/UsersTab.vue';
import { Button } from '@/components/ui/button';
import { useWorkspaceRole } from '@/composables/useWorkspaceRole';
import SettingsLayout from '@/layouts/SettingsLayout.vue';

interface Workspace {
    id: string;
    name: string;
}

interface Member {
    id: string;
    name: string;
    email: string;
    photo_url: string | null;
    role: string;
}

interface Invite {
    id: string;
    email: string;
    role: string;
}

interface Role {
    value: string;
    label: string;
}

defineProps<{
    workspace: Workspace;
    owner: Member;
    members: Member[];
    invites: Invite[];
    roles: Role[];
}>();

const { canManageTeam } = useWorkspaceRole();
const inviteOpen = ref(false);
</script>

<template>
    <Head :title="$t('settings.members.title')" />

    <SettingsLayout
        :title="$t('settings.members.title')"
        :description="$t('settings.workspace.members_description')"
    >
        <template v-if="canManageTeam" #actions>
            <Button data-testid="invite-member-button" @click="inviteOpen = true">
                <IconPlus class="size-4" />
                {{ $t('settings.members.invite.submit') }}
            </Button>
        </template>

        <UsersTab
            v-model:invite-open="inviteOpen"
            :members="members"
            :invitations="invites"
            :roles="roles"
        />
    </SettingsLayout>
</template>
