<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    IconClock,
    IconDotsVertical,
    IconEye,
    IconShield,
    IconTrash,
    IconUser,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InviteMemberDialog from '@/components/members/InviteMemberDialog.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useWorkspaceRole } from '@/composables/useWorkspaceRole';
import { destroy as destroyInvite } from '@/routes/app/invites';
import { remove as removeMemberRoute, updateRole } from '@/routes/app/members';
import { WorkspaceRole } from '@/types/workspace-role';

interface Member {
    id: string;
    name: string;
    email: string;
    photo_url: string | null;
    role: string;
}

interface Invitation {
    id: string;
    email: string;
    role: string;
}

interface Role {
    value: string;
    label: string;
}

defineProps<{
    members: Member[];
    invitations: Invitation[];
    roles: Role[];
}>();

const roleIcon = (role: string) => {
    if (role === WorkspaceRole.Admin) {
        return IconShield;
    }

    if (role === WorkspaceRole.Viewer) {
        return IconEye;
    }

    return IconUser;
};

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

const { canManageTeam } = useWorkspaceRole();

const inviteOpen = defineModel<boolean>('inviteOpen', { default: false });
const removeMemberModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const cancelInvitationModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const changeRole = (member: Member, role: string) => {
    router.put(updateRole.url(member.id), { role });
};
</script>

<template>
    <div class="flex flex-col">
        <ul class="flex flex-col gap-2">
            <li
                v-for="member in members"
                :key="member.id"
                class="flex items-center gap-4 rounded-xl border border-border bg-card p-4"
                :data-testid="`member-row-${member.id}`"
            >
                <Avatar
                    :src="member.photo_url"
                    :name="member.name"
                    class="size-12 rounded-lg"
                    fallback-class="bg-sidebar-accent text-sidebar-accent-foreground"
                />
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <p
                            class="truncate text-sm leading-tight font-emphasis text-foreground"
                        >
                            {{ member.name }}
                        </p>
                        <Badge variant="secondary">
                            {{ $t(`settings.members.roles.${member.role}`) }}
                        </Badge>
                    </div>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ member.email }}
                    </p>
                </div>
                <DropdownMenu v-if="canManageTeam && member.id !== currentUserId">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="shrink-0 text-muted-foreground data-[state=open]:bg-accent"
                            :aria-label="member.name"
                        >
                            <IconDotsVertical class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            v-for="role in roles.filter((r) => r.value !== member.role)"
                            :key="role.value"
                            @click="changeRole(member, role.value)"
                        >
                            <component :is="roleIcon(role.value)" class="size-4" />
                            {{
                                $t('settings.members.make_role', {
                                    role: $t(`settings.members.roles.${role.value}`),
                                })
                            }}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            @click="
                                removeMemberModal?.open({
                                    url: removeMemberRoute.url(member.id),
                                    confirmText: member.email,
                                })
                            "
                        >
                            <IconTrash class="size-4" />
                            {{ $t('settings.members.remove') }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </li>
            <li
                v-for="invitation in invitations"
                :key="`inv-${invitation.id}`"
                class="flex items-center gap-4 rounded-xl border border-border bg-card p-4"
            >
                <span
                    class="inline-flex size-12 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                >
                    <IconClock class="size-5" />
                </span>
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <p
                            class="truncate text-sm leading-tight font-emphasis text-foreground"
                        >
                            {{ invitation.email }}
                        </p>
                        <Badge variant="secondary">
                            {{ $t(`settings.members.roles.${invitation.role}`) }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ $t('settings.members.pending.title') }}
                    </p>
                </div>
                <Button
                    v-if="canManageTeam"
                    variant="ghost"
                    size="icon"
                    class="shrink-0 text-destructive-text"
                    :aria-label="$t('settings.members.cancel_invite_modal.action')"
                    @click="
                        cancelInvitationModal?.open({
                            url: destroyInvite.url(invitation.id),
                            confirmText: invitation.email,
                        })
                    "
                >
                    <IconTrash class="size-4" />
                </Button>
            </li>
        </ul>

        <InviteMemberDialog v-model:open="inviteOpen" />

        <ConfirmDeleteModal
            ref="removeMemberModal"
            :title="$t('settings.members.remove_modal.title')"
            :description="$t('settings.members.remove_modal.description')"
            :action="$t('settings.members.remove_modal.action')"
        />

        <ConfirmDeleteModal
            ref="cancelInvitationModal"
            :title="$t('settings.members.cancel_invite_modal.title')"
            :description="$t('settings.members.cancel_invite_modal.description')"
            :action="$t('settings.members.cancel_invite_modal.action')"
        />
    </div>
</template>
