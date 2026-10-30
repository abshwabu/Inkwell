<script setup>
defineProps({
    html: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div
        class="post-content prose prose-indigo lg:prose-lg max-w-none leading-relaxed dark:prose-invert break-words"
        v-html="html"
    />
</template>

<style>
/* Task list styling */
.post-content ul[data-type="taskList"] {
    list-style: none;
    padding-left: 0;
    margin: 1.25rem 0;
}

.post-content ul[data-type="taskList"] li {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin: 0.35rem 0;
}

.post-content ul[data-type="taskList"] li > label {
    flex-shrink: 0;
    margin-top: 0.25rem;
    user-select: none;
}

.post-content ul[data-type="taskList"] li > label input[type="checkbox"] {
    border-radius: 0.25rem;
    border-color: #d1d5db;
    color: #4f46e5;
    cursor: default;
}

.dark .post-content ul[data-type="taskList"] li > label input[type="checkbox"] {
    border-color: #4b5563;
    background-color: #1f2937;
}

.post-content ul[data-type="taskList"] li > div {
    flex: 1 1 auto;
}

/* Table styling */
.post-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 1.5rem 0;
    overflow-x: auto;
    display: table;
    border: 1px solid #e5e7eb;
}

.dark .post-content table {
    border-color: #374151;
}

.post-content th {
    background-color: #f9fafb;
    font-weight: 600;
    text-align: left;
    padding: 0.75rem 1rem;
    border: 1px solid #e5e7eb;
}

.dark .post-content th {
    background-color: #1f2937;
    border-color: #374151;
    color: #f3f4f6;
}

.post-content td {
    padding: 0.75rem 1rem;
    border: 1px solid #e5e7eb;
    vertical-align: top;
}

.dark .post-content td {
    border-color: #374151;
}

/* Code block styling */
.post-content pre {
    background-color: #1e1e2e;
    color: #cdd6f4;
    padding: 1.25rem;
    border-radius: 0.75rem;
    overflow-x: auto;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.9em;
    line-height: 1.6;
}

.post-content pre code {
    background: transparent;
    padding: 0;
    color: inherit;
    font-size: inherit;
}

/* Image alignment & caption */
.post-content img {
    border-radius: 0.75rem;
    max-width: 100%;
    height: auto;
    margin: 1.5rem 0;
}

.post-content img[data-align="center"] {
    display: block;
    margin-left: auto;
    margin-right: auto;
}

.post-content img[data-align="left"] {
    float: left;
    margin-right: 1.5rem;
    margin-bottom: 1rem;
    margin-top: 0.5rem;
}

.post-content img[data-align="right"] {
    float: right;
    margin-left: 1.5rem;
    margin-bottom: 1rem;
    margin-top: 0.5rem;
}

/* YouTube embeds */
.post-content iframe {
    width: 100%;
    aspect-ratio: 16 / 9;
    border-radius: 0.75rem;
    margin: 1.5rem 0;
}
</style>
