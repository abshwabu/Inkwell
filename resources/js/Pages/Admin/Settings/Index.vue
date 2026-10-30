<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    site_name: props.settings.site_name || 'Inkwell',
    tagline: props.settings.tagline || '',
    logo_path: props.settings.logo_path || '',
    default_meta_title: props.settings.default_meta_title || '',
    default_meta_description: props.settings.default_meta_description || '',
    signups_enabled: Boolean(props.settings.signups_enabled),
    require_email_verification: Boolean(props.settings.require_email_verification),
    comment_moderation_mode: props.settings.comment_moderation_mode || 'open',
    blocked_words: props.settings.blocked_words || '',
    daily_post_limit_new_users: Number(props.settings.daily_post_limit_new_users) || 3,
    link_limit_new_users_comments: Number(props.settings.link_limit_new_users_comments) || 1,
});

const saveSettings = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Site Settings" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-200 pb-5 dark:border-gray-800">
                <div>
                    <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Site Settings
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Configure brand identity, default SEO metadata, access policies, and anti-spam protection.
                    </p>
                </div>
            </div>

            <form @submit.prevent="saveSettings" class="space-y-8">
                <!-- Brand & Identity -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="font-serif text-lg font-bold text-gray-900 dark:text-white mb-4">
                        Brand Identity & Metadata
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 text-xs">
                        <div>
                            <label class="block font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Platform Name
                            </label>
                            <input
                                v-model="form.site_name"
                                type="text"
                                required
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Tagline
                            </label>
                            <input
                                v-model="form.tagline"
                                type="text"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Default SEO Title
                            </label>
                            <input
                                v-model="form.default_meta_title"
                                type="text"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Default SEO Description
                            </label>
                            <textarea
                                v-model="form.default_meta_description"
                                rows="2"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Registration & Moderation Mode -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="font-serif text-lg font-bold text-gray-900 dark:text-white mb-4">
                        Access & Moderation Policies
                    </h3>
                    <div class="space-y-4 text-xs">
                        <!-- Signups on/off -->
                        <div class="flex items-center justify-between p-3 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40">
                            <div>
                                <p class="font-bold text-gray-900 dark:text-white text-sm">Allow Public Registrations</p>
                                <p class="text-gray-400">When disabled, new visitor signups are blocked.</p>
                            </div>
                            <input
                                v-model="form.signups_enabled"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                            />
                        </div>

                        <!-- Email verification toggle -->
                        <div class="flex items-center justify-between p-3 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40">
                            <div>
                                <p class="font-bold text-gray-900 dark:text-white text-sm">Require Email Verification</p>
                                <p class="text-gray-400">Users must click email verification links before publishing.</p>
                            </div>
                            <input
                                v-model="form.require_email_verification"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                            />
                        </div>

                        <!-- Comment Moderation Mode -->
                        <div class="p-4 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40">
                            <label class="block font-bold text-gray-900 dark:text-white text-sm mb-1">
                                Comment Moderation Mode
                            </label>
                            <p class="text-gray-400 mb-3">Controls whether new comments publish immediately or require first-time approval.</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label
                                    class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition"
                                    :class="form.comment_moderation_mode === 'open' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30' : 'border-gray-200 dark:border-gray-700'"
                                >
                                    <input
                                        type="radio"
                                        value="open"
                                        v-model="form.comment_moderation_mode"
                                        class="text-indigo-600"
                                    />
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">Open Mode</p>
                                        <p class="text-[11px] text-gray-400">All comments post immediately.</p>
                                    </div>
                                </label>

                                <label
                                    class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition"
                                    :class="form.comment_moderation_mode === 'hold_first_comment' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30' : 'border-gray-200 dark:border-gray-700'"
                                >
                                    <input
                                        type="radio"
                                        value="hold_first_comment"
                                        v-model="form.comment_moderation_mode"
                                        class="text-indigo-600"
                                    />
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">Hold First Comment</p>
                                        <p class="text-[11px] text-gray-400">First comment per account held for review.</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Spam Protection & Restrictions -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="font-serif text-lg font-bold text-gray-900 dark:text-white mb-4">
                        Anti-Spam & New Account Rate Limits
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 text-xs">
                        <div>
                            <label class="block font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Daily Story Limit (New Accounts &le; 3 Days)
                            </label>
                            <input
                                v-model.number="form.daily_post_limit_new_users"
                                type="number"
                                min="1"
                                max="50"
                                required
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Max Links in Comments (New Accounts)
                            </label>
                            <input
                                v-model.number="form.link_limit_new_users_comments"
                                type="number"
                                min="0"
                                max="20"
                                required
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Blocked Keywords (Comma-Separated)
                            </label>
                            <textarea
                                v-model="form.blocked_words"
                                rows="3"
                                placeholder="casino,viagra,free-crypto,loan-scam"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white font-mono"
                            ></textarea>
                            <p class="mt-1 text-[11px] text-gray-400">Submissions containing any of these keywords will be rejected on submission.</p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-xl bg-indigo-600 px-6 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 transition"
                    >
                        {{ form.processing ? 'Saving...' : 'Save All Settings' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
