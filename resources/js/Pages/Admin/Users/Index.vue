<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    roles: {
        type: Array,
        default: () => ['user', 'moderator', 'admin'],
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const isAdmin = computed(() => currentUser.value?.role === 'admin');

const searchQuery = ref(props.filters.q || '');
const selectedRole = ref(props.filters.role || '');
const selectedStatus = ref(props.filters.status || '');

const applyFilters = () => {
    router.get(route('admin.users.index'), {
        q: searchQuery.value || undefined,
        role: selectedRole.value || undefined,
        status: selectedStatus.value || undefined,
    }, { preserveState: true, replace: true });
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedRole.value = '';
    selectedStatus.value = '';
    applyFilters();
};

// Role change modal
const roleModalOpen = ref(false);
const activeUser = ref(null);
const targetRole = ref('user');

const openRoleModal = (user) => {
    activeUser.value = user;
    targetRole.value = user.role;
    roleModalOpen.value = true;
};

const updateRole = () => {
    if (!activeUser.value) return;
    router.post(route('admin.users.role', activeUser.value.id), {
        role: targetRole.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { roleModalOpen.value = false; },
    });
};

// Ban / Unban modal
const banModalOpen = ref(false);
const banReason = ref('');

const openBanModal = (user) => {
    activeUser.value = user;
    banReason.value = 'Spamming or violation of community standards.';
    banModalOpen.value = true;
};

const submitBan = () => {
    if (!activeUser.value || !banReason.value.trim()) return;
    router.post(route('admin.users.ban', activeUser.value.id), {
        reason: banReason.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => { banModalOpen.value = false; },
    });
};

const unbanUser = (user) => {
    if (confirm(`Unban ${user.name}?`)) {
        router.post(route('admin.users.unban', user.id), {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Manage Users" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-200 pb-5 dark:border-gray-800">
                <div>
                    <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        User Management
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Inspect registered writers and readers, adjust staff roles, and enforce moderation bans.
                    </p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search name, username, or email..."
                        @keyup.enter="applyFilters"
                        class="w-full sm:w-72 rounded-xl border-gray-200 bg-gray-50 text-xs text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />

                    <select
                        v-model="selectedRole"
                        @change="applyFilters"
                        class="rounded-xl border-gray-200 bg-gray-50 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="">All Roles</option>
                        <option value="user">User</option>
                        <option value="moderator">Moderator</option>
                        <option value="admin">Admin</option>
                    </select>

                    <select
                        v-model="selectedStatus"
                        @change="applyFilters"
                        class="rounded-xl border-gray-200 bg-gray-50 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="banned">Banned</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="applyFilters"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 transition"
                    >
                        Filter
                    </button>
                    <button
                        type="button"
                        @click="resetFilters"
                        class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 transition"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Users Table -->
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50/75 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-3.5">User</th>
                                <th class="px-4 py-3.5">Role</th>
                                <th class="px-4 py-3.5">Activity</th>
                                <th class="px-4 py-3.5">Status</th>
                                <th class="px-4 py-3.5">Joined</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition">
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <img :src="user.avatar_url" class="h-9 w-9 rounded-full object-cover" />
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ user.name }}</p>
                                            <p class="text-[11px] text-gray-400">@{{ user.username }} • {{ user.email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span
                                        :class="{
                                            'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300': user.role === 'admin',
                                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300': user.role === 'moderator',
                                            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300': user.role === 'user',
                                        }"
                                        class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                    >
                                        {{ user.role }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    {{ user.posts_count || 0 }} stories • {{ user.comments_count || 0 }} comments
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span
                                        v-if="user.is_banned"
                                        class="rounded bg-rose-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-rose-700 dark:bg-rose-950 dark:text-rose-400"
                                        :title="user.ban_reason"
                                    >
                                        Banned
                                    </span>
                                    <span
                                        v-else
                                        class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                    >
                                        Active
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-gray-400">
                                    {{ new Date(user.created_at).toLocaleDateString() }}
                                </td>
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <Link
                                            :href="route('admin.users.show', user.id)"
                                            class="rounded px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                        >
                                            Activity
                                        </Link>

                                        <!-- Change Role (Admin Only) -->
                                        <button
                                            v-if="isAdmin && user.id !== currentUser.id"
                                            type="button"
                                            @click="openRoleModal(user)"
                                            class="rounded px-2.5 py-1 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/40"
                                        >
                                            Role
                                        </button>

                                        <!-- Ban / Unban -->
                                        <template v-if="user.id !== currentUser.id">
                                            <button
                                                v-if="!user.is_banned"
                                                type="button"
                                                @click="openBanModal(user)"
                                                class="rounded px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                                            >
                                                Ban
                                            </button>
                                            <button
                                                v-else
                                                type="button"
                                                @click="unbanUser(user)"
                                                class="rounded px-2.5 py-1 text-xs font-semibold text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/40"
                                            >
                                                Unban
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="users.links && users.links.length > 3" class="flex justify-center gap-1 border-t border-gray-100 p-4 dark:border-gray-800">
                    <Link
                        v-for="(link, i) in users.links"
                        :key="i"
                        :href="link.url || '#'"
                        :class="[
                            link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200',
                            'px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-medium'
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Role Change Modal -->
        <div
            v-if="roleModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @click.self="roleModalOpen = false"
        >
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">
                    Update User Role
                </h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Target: <strong class="text-gray-900 dark:text-white">{{ activeUser?.name }}</strong> (@{{ activeUser?.username }})
                </p>

                <div class="mt-4">
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                        Select Role:
                    </label>
                    <select
                        v-model="targetRole"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white capitalize"
                    >
                        <option v-for="role in roles" :key="role" :value="role">
                            {{ role }}
                        </option>
                    </select>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="roleModalOpen = false"
                        class="rounded-xl px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="updateRole"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                    >
                        Save Role
                    </button>
                </div>
            </div>
        </div>

        <!-- Ban User Modal -->
        <div
            v-if="banModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @click.self="banModalOpen = false"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
                <h3 class="text-base font-bold text-rose-600 dark:text-rose-400">
                    Suspend User Account
                </h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Banning <strong class="text-gray-900 dark:text-white">{{ activeUser?.name }}</strong> will immediately block all write operations (publishing, commenting, liking, following).
                </p>

                <div class="mt-4">
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                        Reason for Suspension:
                    </label>
                    <textarea
                        v-model="banReason"
                        rows="3"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-rose-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        required
                    ></textarea>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="banModalOpen = false"
                        class="rounded-xl px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitBan"
                        class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-rose-500 transition"
                    >
                        Confirm Suspension
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
