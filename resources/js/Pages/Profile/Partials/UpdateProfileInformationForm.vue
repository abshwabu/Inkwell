<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const avatarPreview = ref(null);
const avatarInput = ref(null);

const form = useForm({
    name: user.name,
    username: user.username,
    email: user.email,
    bio: user.bio || '',
    avatar: null,
});

const handleAvatarChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.avatar = file;
        avatarPreview.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        _method: 'patch',
    })).post(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            avatarPreview.value = null;
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Profile & Public Persona
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Update your account's public identity, avatar, bio, and email address.
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <!-- Avatar Upload with Square Preview -->
            <div>
                <InputLabel for="avatar" value="Profile Avatar (Resized to 256×256)" />

                <div class="mt-2 flex items-center gap-5">
                    <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-full border-2 border-indigo-200 dark:border-indigo-800 shadow-sm bg-gray-100 dark:bg-gray-800">
                        <img
                            :src="avatarPreview || user.avatar_url"
                            :alt="user.name"
                            class="h-full w-full object-cover"
                        />
                    </div>

                    <div>
                        <input
                            ref="avatarInput"
                            id="avatar"
                            type="file"
                            accept="image/png,image/jpeg,image/webp,image/gif"
                            class="hidden"
                            @change="handleAvatarChange"
                        />
                        <button
                            type="button"
                            @click="avatarInput.click()"
                            class="rounded-lg border border-gray-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            Choose New Avatar
                        </button>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Square crop applied automatically. Supports PNG, JPG, or WebP.
                        </p>
                    </div>
                </div>

                <InputError class="mt-2" :message="form.errors.avatar" />
            </div>

            <!-- Name -->
            <div>
                <InputLabel for="name" value="Full Name" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <!-- Username -->
            <div>
                <InputLabel for="username" value="Username (Public Profile URL: /@{username})" />

                <div class="relative mt-1">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-400">
                        @
                    </span>
                    <TextInput
                        id="username"
                        type="text"
                        class="block w-full pl-8"
                        v-model="form.username"
                        required
                        autocomplete="username"
                    />
                </div>

                <InputError class="mt-2" :message="form.errors.username" />
            </div>

            <!-- Email -->
            <div>
                <InputLabel for="email" value="Email Address" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <!-- Bio -->
            <div>
                <InputLabel for="bio" value="Bio" />

                <textarea
                    id="bio"
                    rows="3"
                    v-model="form.bio"
                    placeholder="Tell your readers a little about yourself, your background, or what you write about..."
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600 sm:text-sm"
                ></textarea>

                <InputError class="mt-2" :message="form.errors.bio" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="text-sm text-amber-600 dark:text-amber-400">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600 dark:text-green-400"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save Profile</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-green-600 dark:text-green-400 font-medium"
                    >
                        Saved successfully.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
