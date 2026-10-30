<script setup>
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    categories: {
        type: Array,
        required: true,
    },
    tags: {
        type: Array,
        default: () => [],
    },
    preferredCategoryIds: {
        type: Array,
        default: () => [],
    },
    preferredTagIds: {
        type: Array,
        default: () => [],
    },
});

const selectedCategories = ref([...props.preferredCategoryIds]);
const selectedTags = ref([...props.preferredTagIds]);

const form = useForm({
    categories: selectedCategories.value,
    tags: selectedTags.value,
});

const toggleCategory = (id) => {
    const index = selectedCategories.value.indexOf(id);
    if (index > -1) {
        selectedCategories.value.splice(index, 1);
    } else {
        selectedCategories.value.push(id);
    }
    form.categories = selectedCategories.value;
};

const toggleTag = (id) => {
    const index = selectedTags.value.indexOf(id);
    if (index > -1) {
        selectedTags.value.splice(index, 1);
    } else {
        selectedTags.value.push(id);
    }
    form.tags = selectedTags.value;
};

const submit = () => {
    form.categories = selectedCategories.value;
    form.tags = selectedTags.value;
    form.put(route('settings.preferences.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Reading Preferences — Inkwell" />

    <AppLayout>
        <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="border-b border-gray-200 pb-6 dark:border-gray-800">
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <Link :href="route('profile.edit')" class="hover:text-indigo-600">Settings</Link>
                    <span>/</span>
                    <span class="text-gray-900 dark:text-gray-200 font-medium">Reading Preferences</span>
                </div>
                <h1 class="mt-3 font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Reading Preferences
                </h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    Choose the topics and tags you love to calibrate your "For you" feed algorithm.
                </p>
            </div>

            <form @submit.prevent="submit" class="mt-8 space-y-10">
                <!-- Categories -->
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">
                        Preferred Topics ({{ selectedCategories.length }} selected)
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            type="button"
                            @click="toggleCategory(cat.id)"
                            :class="[
                                selectedCategories.includes(cat.id)
                                    ? 'border-indigo-600 ring-2 ring-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200'
                                    : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-gray-700',
                                'rounded-2xl border p-4 text-left transition shadow-xs flex flex-col justify-between'
                            ]"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: cat.color || '#4F46E5' }"></span>
                                    <span v-if="selectedCategories.includes(cat.id)" class="text-xs font-bold text-indigo-600 dark:text-indigo-400">✓</span>
                                </div>
                                <h3 class="mt-2 font-serif text-base font-bold text-gray-900 dark:text-white">
                                    {{ cat.name }}
                                </h3>
                                <p v-if="cat.description" class="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                                    {{ cat.description }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Tags -->
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">
                        Preferred Tags ({{ selectedTags.length }} selected)
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tag in tags"
                            :key="tag.id"
                            type="button"
                            @click="toggleTag(tag.id)"
                            :class="[
                                selectedTags.includes(tag.id)
                                    ? 'bg-indigo-600 text-white font-semibold'
                                    : 'bg-white border border-gray-200 text-gray-700 hover:border-indigo-300 dark:bg-gray-900 dark:border-gray-800 dark:text-gray-300',
                                'rounded-xl px-3 py-1.5 text-xs transition shadow-xs'
                            ]"
                        >
                            #{{ tag.name }}
                        </button>
                    </div>
                </div>

                <!-- Save Action -->
                <div class="flex items-center justify-between border-t border-gray-200 pt-6 dark:border-gray-800">
                    <span v-if="form.recentlySuccessful" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                        Preferences saved! Your feed has been updated.
                    </span>
                    <span v-else></span>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 transition"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Preferences' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
