<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

const props = defineProps({
    categories: {
        type: Array,
        required: true,
    },
    tags: {
        type: Array,
        default: () => [],
    },
    userCategories: {
        type: Array,
        default: () => [],
    },
    userTags: {
        type: Array,
        default: () => [],
    },
});

const selectedCategories = ref([...props.userCategories]);
const selectedTags = ref([...props.userTags]);

const form = useForm({
    categories: selectedCategories.value,
    tags: selectedTags.value,
    skip: false,
});

const isMinimumSelected = computed(() => selectedCategories.value.length >= 3);

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
    if (!isMinimumSelected.value) return;
    form.categories = selectedCategories.value;
    form.tags = selectedTags.value;
    form.skip = false;
    form.post(route('onboarding.store'));
};

const skip = () => {
    form.skip = true;
    form.post(route('onboarding.store'));
};
</script>

<template>
    <Head title="Personalize Your Feed — Inkwell" />

    <div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8 dark:bg-gray-950 text-gray-900 dark:text-gray-100 flex flex-col justify-between">
        <div class="mx-auto max-w-3xl w-full">
            <!-- Header -->
            <div class="text-center">
                <div class="flex justify-center">
                    <Link :href="route('home')">
                        <ApplicationLogo />
                    </Link>
                </div>
                <h1 class="mt-6 font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                    What are you curious about?
                </h1>
                <p class="mt-3 text-base text-gray-600 dark:text-gray-300 max-w-xl mx-auto">
                    Select <span class="font-bold text-indigo-600 dark:text-indigo-400">at least 3 topics</span> to curate your personalized reading feed. You can adjust these anytime.
                </p>

                <!-- Selection Counter Badge -->
                <div class="mt-4 inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-semibold"
                    :class="[
                        isMinimumSelected
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                            : 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                    ]"
                >
                    <span class="h-2 w-2 rounded-full" :class="isMinimumSelected ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                    <span>Selected {{ selectedCategories.length }} of 3 minimum topics</span>
                </div>
            </div>

            <!-- Categories Selection Grid -->
            <div class="mt-10">
                <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">
                    Categories
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
                            'relative rounded-2xl border p-4 text-left transition shadow-xs flex flex-col justify-between'
                        ]"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: cat.color || '#4F46E5' }"></span>
                                <span
                                    v-if="selectedCategories.includes(cat.id)"
                                    class="text-xs font-bold text-indigo-600 dark:text-indigo-400"
                                >
                                    ✓
                                </span>
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
                <div v-if="form.errors.categories" class="mt-2 text-xs text-red-600 dark:text-red-400">
                    {{ form.errors.categories }}
                </div>
            </div>

            <!-- Optional Tags Chips -->
            <div v-if="tags && tags.length > 0" class="mt-10">
                <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">
                    Specific Interests (Optional)
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

            <!-- Bottom Action Controls -->
            <div class="mt-12 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-gray-200 pt-6 dark:border-gray-800">
                <button
                    type="button"
                    @click="skip"
                    :disabled="form.processing"
                    class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition"
                >
                    Skip for now
                </button>

                <button
                    type="button"
                    @click="submit"
                    :disabled="!isMinimumSelected || form.processing"
                    class="w-full sm:w-auto rounded-xl bg-indigo-600 px-8 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-40 disabled:hover:bg-indigo-600 transition"
                >
                    {{ form.processing ? 'Personalizing...' : 'Build My Feed' }}
                </button>
            </div>
        </div>

        <footer class="mt-16 text-center text-xs text-gray-400">
            &copy; {{ new Date().getFullYear() }} Inkwell. You can update your reading preferences anytime in Settings.
        </footer>
    </div>
</template>
