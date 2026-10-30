<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import { BubbleMenu } from '@tiptap/vue-3/menus';
import { StarterKit } from '@tiptap/starter-kit';
import { Underline } from '@tiptap/extension-underline';
import { Link } from '@tiptap/extension-link';
import { TextAlign } from '@tiptap/extension-text-align';
import { Highlight } from '@tiptap/extension-highlight';
import { Placeholder } from '@tiptap/extension-placeholder';
import { Table } from '@tiptap/extension-table';
import { TableRow } from '@tiptap/extension-table-row';
import { TableHeader } from '@tiptap/extension-table-header';
import { TableCell } from '@tiptap/extension-table-cell';
import { TaskList } from '@tiptap/extension-task-list';
import { TaskItem } from '@tiptap/extension-task-item';
import { CodeBlockLowlight } from '@tiptap/extension-code-block-lowlight';
import { CharacterCount } from '@tiptap/extension-character-count';
import { Youtube } from '@tiptap/extension-youtube';
import { TextStyle } from '@tiptap/extension-text-style';
import { Color } from '@tiptap/extension-color';
import { common, createLowlight } from 'lowlight';
import axios from 'axios';

import CustomImage from './CustomImage';
import SlashCommandExtension from './SlashCommandExtension';

const props = defineProps({
    modelValue: {
        type: [Object, String],
        default: null,
    },
    json: {
        type: Object,
        default: null,
    },
    html: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Write your story... Type / for commands',
    },
});

const emit = defineEmits(['update:modelValue', 'update:json', 'update:html', 'change']);

// Lowlight instance for code highlighting
const lowlight = createLowlight(common);

// Modals and dialog state
const showLinkModal = ref(false);
const linkUrl = ref('');
const linkOpenInNewTab = ref(false);

const showImageModal = ref(false);
const imageUrl = ref('');
const imageAlt = ref('');
const imageCaption = ref('');
const imageAlign = ref('center');
const imageWidth = ref('100%');
const isUploadingImage = ref(false);
const imageUploadError = ref('');
const fileInputRef = ref(null);

const showYoutubeModal = ref(false);
const youtubeUrl = ref('');

const showColorPicker = ref(false);
const selectedColor = ref('#000000');

// Slash command state
const slashMenu = ref({
    isOpen: false,
    query: '',
    selectedIndex: 0,
    command: null,
    coords: { top: 0, left: 0 },
});

// Slash command items definition
const slashCommands = [
    {
        title: 'Heading 1',
        description: 'Large section heading',
        icon: 'H1',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).setHeading({ level: 1 }).run();
        },
    },
    {
        title: 'Heading 2',
        description: 'Medium section heading',
        icon: 'H2',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).setHeading({ level: 2 }).run();
        },
    },
    {
        title: 'Heading 3',
        description: 'Small section heading',
        icon: 'H3',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).setHeading({ level: 3 }).run();
        },
    },
    {
        title: 'Paragraph',
        description: 'Plain body text',
        icon: '¶',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).setParagraph().run();
        },
    },
    {
        title: 'Bullet List',
        description: 'Create a simple bulleted list',
        icon: '•',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).toggleBulletList().run();
        },
    },
    {
        title: 'Numbered List',
        description: 'Create a sequential numbered list',
        icon: '1.',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).toggleOrderedList().run();
        },
    },
    {
        title: 'Task List',
        description: 'Track tasks with checkboxes',
        icon: '☑',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).toggleTaskList().run();
        },
    },
    {
        title: 'Quote',
        description: 'Capture a meaningful quote',
        icon: '“',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).toggleBlockquote().run();
        },
    },
    {
        title: 'Code Block',
        description: 'Code snippet with syntax highlight',
        icon: '{ }',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).toggleCodeBlock().run();
        },
    },
    {
        title: 'Table',
        description: 'Insert a 3x3 table',
        icon: '▦',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
        },
    },
    {
        title: 'Image',
        description: 'Upload or insert an image',
        icon: '🖼',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).run();
            openImageModal();
        },
    },
    {
        title: 'YouTube Video',
        description: 'Embed a video from YouTube',
        icon: '▶',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).run();
            openYoutubeModal();
        },
    },
    {
        title: 'Divider',
        description: 'Visually separate sections',
        icon: '—',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).setHorizontalRule().run();
        },
    },
];

const filteredSlashCommands = computed(() => {
    const q = (slashMenu.value.query || '').toLowerCase().trim();
    if (!q) return slashCommands;
    return slashCommands.filter(
        (cmd) => cmd.title.toLowerCase().includes(q) || cmd.description.toLowerCase().includes(q)
    );
});

// Upload image file helper
const uploadImageFile = async (file) => {
    isUploadingImage.value = true;
    imageUploadError.value = '';

    const formData = new FormData();
    formData.append('image', file);

    try {
        const response = await axios.post(route('uploads.images'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        isUploadingImage.value = false;
        return response.data.url;
    } catch (err) {
        isUploadingImage.value = false;
        imageUploadError.value = err.response?.data?.message || 'Failed to upload image.';
        throw err;
    }
};

// Initial editor content resolution
const initialContent = props.json || props.html || props.modelValue || '';

// TipTap Editor instance
const editor = useEditor({
    content: initialContent,
    extensions: [
        StarterKit.configure({
            codeBlock: false,
        }),
        Underline,
        Link.configure({
            openOnClick: false,
            autolink: true,
            defaultProtocol: 'https',
            HTMLAttributes: {
                rel: 'noopener noreferrer nofollow ugc',
            },
        }),
        CustomImage,
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        Highlight.configure({
            multicolor: true,
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
        Table.configure({
            resizable: true,
        }),
        TableRow,
        TableHeader,
        TableCell,
        TaskList,
        TaskItem.configure({
            nested: true,
        }),
        CodeBlockLowlight.configure({
            lowlight,
        }),
        CharacterCount,
        Youtube.configure({
            inline: false,
            nocookie: true,
            HTMLAttributes: {
                class: 'aspect-video w-full rounded-xl',
            },
        }),
        TextStyle,
        Color,
        SlashCommandExtension.configure({
            suggestion: {
                items: ({ query }) => {
                    return slashCommands.filter((cmd) =>
                        cmd.title.toLowerCase().includes(query.toLowerCase())
                    );
                },
                render: () => {
                    return {
                        onStart: (props) => {
                            const rect = props.clientRect ? props.clientRect() : null;
                            slashMenu.value = {
                                isOpen: true,
                                query: props.query,
                                selectedIndex: 0,
                                command: props.command,
                                coords: rect
                                    ? { top: rect.bottom + window.scrollY, left: rect.left + window.scrollX }
                                    : { top: 0, left: 0 },
                            };
                        },
                        onUpdate: (props) => {
                            const rect = props.clientRect ? props.clientRect() : null;
                            slashMenu.value.query = props.query;
                            if (rect) {
                                slashMenu.value.coords = {
                                    top: rect.bottom + window.scrollY,
                                    left: rect.left + window.scrollX,
                                };
                            }
                        },
                        onKeyDown: (props) => {
                            if (props.event.key === 'ArrowUp') {
                                slashMenu.value.selectedIndex =
                                    (slashMenu.value.selectedIndex + filteredSlashCommands.value.length - 1) %
                                    filteredSlashCommands.value.length;
                                return true;
                            }
                            if (props.event.key === 'ArrowDown') {
                                slashMenu.value.selectedIndex =
                                    (slashMenu.value.selectedIndex + 1) % filteredSlashCommands.value.length;
                                return true;
                            }
                            if (props.event.key === 'Enter') {
                                const selected = filteredSlashCommands.value[slashMenu.value.selectedIndex];
                                if (selected && slashMenu.value.command) {
                                    slashMenu.value.command(selected);
                                    slashMenu.value.isOpen = false;
                                    return true;
                                }
                            }
                            if (props.event.key === 'Escape') {
                                slashMenu.value.isOpen = false;
                                return true;
                            }
                            return false;
                        },
                        onExit: () => {
                            slashMenu.value.isOpen = false;
                        },
                    };
                },
            },
        }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-indigo lg:prose-lg dark:prose-invert max-w-none focus:outline-none min-h-[360px] px-6 py-6',
        },
        // Paste image handler
        handlePaste: (view, event) => {
            const items = Array.from(event.clipboardData?.items || []);
            const imageItem = items.find((item) => item.type.startsWith('image/'));
            if (imageItem) {
                event.preventDefault();
                const file = imageItem.getAsFile();
                if (file) {
                    uploadImageFile(file).then((url) => {
                        editor.value.chain().focus().setImage({ src: url, alt: file.name, align: 'center', width: '100%' }).run();
                    });
                }
                return true;
            }
            return false;
        },
        // Drag and drop image handler
        handleDrop: (view, event, slice, moved) => {
            if (!moved && event.dataTransfer?.files?.length > 0) {
                const files = Array.from(event.dataTransfer.files);
                const imageFile = files.find((f) => f.type.startsWith('image/'));
                if (imageFile) {
                    event.preventDefault();
                    uploadImageFile(imageFile).then((url) => {
                        editor.value.chain().focus().setImage({ src: url, alt: imageFile.name, align: 'center', width: '100%' }).run();
                    });
                    return true;
                }
            }
            return false;
        },
    },
    onUpdate: ({ editor }) => {
        const json = editor.getJSON();
        const html = editor.getHTML();
        emit('update:modelValue', html);
        emit('update:json', json);
        emit('update:html', html);
        emit('change', { json, html });
    },
});

onBeforeUnmount(() => {
    if (editor.value) {
        editor.value.destroy();
    }
});

// Watch external changes
watch(
    () => props.html,
    (newVal) => {
        if (editor.value && newVal !== editor.value.getHTML()) {
            editor.value.commands.setContent(newVal, false);
        }
    }
);

// Link Dialog Helpers
const openLinkModal = () => {
    if (!editor.value) return;
    const previousUrl = editor.value.getAttributes('link').href || '';
    linkUrl.value = previousUrl;
    linkOpenInNewTab.value = editor.value.getAttributes('link').target === '_blank';
    showLinkModal.value = true;
};

const saveLink = () => {
    if (!editor.value) return;
    if (linkUrl.value.trim() === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        let url = linkUrl.value.trim();
        if (!/^https?:\/\//i.test(url) && !/^\//.test(url) && !/^mailto:/i.test(url)) {
            url = 'https://' + url;
        }
        editor.value
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({
                href: url,
                target: linkOpenInNewTab.value ? '_blank' : null,
            })
            .run();
    }
    showLinkModal.value = false;
};

const removeLink = () => {
    if (!editor.value) return;
    editor.value.chain().focus().unsetLink().run();
    showLinkModal.value = false;
};

// Image Dialog Helpers
const openImageModal = () => {
    imageUrl.value = '';
    imageAlt.value = '';
    imageCaption.value = '';
    imageAlign.value = 'center';
    imageWidth.value = '100%';
    imageUploadError.value = '';

    // If an image is selected, load its attrs
    if (editor.value?.isActive('image')) {
        const attrs = editor.value.getAttributes('image');
        imageUrl.value = attrs.src || '';
        imageAlt.value = attrs.alt || '';
        imageCaption.value = attrs.caption || '';
        imageAlign.value = attrs.align || 'center';
        imageWidth.value = attrs.width || '100%';
    }

    showImageModal.value = true;
};

const handleImageFileSelect = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
        const uploadedUrl = await uploadImageFile(file);
        imageUrl.value = uploadedUrl;
        if (!imageAlt.value) {
            imageAlt.value = file.name.replace(/\.[^/.]+$/, '');
        }
    } catch (err) {
        // error already handled in uploadImageFile
    }
};

const insertImage = () => {
    if (!imageUrl.value.trim() || !editor.value) return;
    editor.value
        .chain()
        .focus()
        .setImage({
            src: imageUrl.value.trim(),
            alt: imageAlt.value.trim(),
            caption: imageCaption.value.trim(),
            align: imageAlign.value,
            width: imageWidth.value,
        })
        .run();
    showImageModal.value = false;
};

// YouTube Dialog Helpers
const openYoutubeModal = () => {
    youtubeUrl.value = '';
    showYoutubeModal.value = true;
};

const insertYoutube = () => {
    if (!youtubeUrl.value.trim() || !editor.value) return;
    editor.value.chain().focus().setYoutubeVideo({ src: youtubeUrl.value.trim() }).run();
    showYoutubeModal.value = false;
};

// Color Picker Helpers
const applyColor = (color) => {
    if (!editor.value) return;
    if (color) {
        editor.value.chain().focus().setColor(color).run();
    } else {
        editor.value.chain().focus().unsetColor().run();
    }
    showColorPicker.value = false;
};

// Code block language options
const codeLanguages = [
    { label: 'Plain Text', value: 'plaintext' },
    { label: 'JavaScript', value: 'javascript' },
    { label: 'TypeScript', value: 'typescript' },
    { label: 'Python', value: 'python' },
    { label: 'PHP', value: 'php' },
    { label: 'HTML', value: 'html' },
    { label: 'CSS', value: 'css' },
    { label: 'JSON', value: 'json' },
    { label: 'Bash', value: 'bash' },
    { label: 'SQL', value: 'sql' },
    { label: 'Markdown', value: 'markdown' },
    { label: 'Rust', value: 'rust' },
    { label: 'Go', value: 'go' },
    { label: 'Java', value: 'java' },
    { label: 'C++', value: 'cpp' },
];

const setCodeLanguage = (lang) => {
    if (!editor.value) return;
    editor.value.chain().focus().updateAttributes('codeBlock', { language: lang }).run();
};

const currentCodeLanguage = computed(() => {
    if (!editor.value || !editor.value.isActive('codeBlock')) return 'plaintext';
    return editor.value.getAttributes('codeBlock').language || 'plaintext';
});

// Block type handling
const currentBlockType = computed(() => {
    if (!editor.value) return 'paragraph';
    for (let level = 1; level <= 6; level++) {
        if (editor.value.isActive('heading', { level })) {
            return `h${level}`;
        }
    }
    return 'paragraph';
});

const setBlockType = (type) => {
    if (!editor.value) return;
    if (type === 'paragraph') {
        editor.value.chain().focus().setParagraph().run();
    } else if (type.startsWith('h')) {
        const level = parseInt(type.replace('h', ''), 10);
        editor.value.chain().focus().toggleHeading({ level }).run();
    }
};

// Execute slash command from menu
const executeSlashCommand = (item) => {
    if (slashMenu.value.command) {
        slashMenu.value.command(item);
    }
    slashMenu.value.isOpen = false;
};
</script>

<template>
    <div class="rich-editor-container border border-gray-200 dark:border-gray-800 rounded-2xl bg-white dark:bg-gray-900 shadow-sm overflow-hidden flex flex-col">
        <!-- FIXED TOOLBAR (Scrolls horizontally on mobile) -->
        <div
            v-if="editor"
            class="editor-toolbar sticky top-0 z-10 border-b border-gray-200 dark:border-gray-800 bg-gray-50/95 dark:bg-gray-900/95 backdrop-blur px-3 py-2 flex items-center gap-1.5 overflow-x-auto flex-nowrap scrollbar-thin"
        >
            <!-- Block Format Dropdown -->
            <select
                :value="currentBlockType"
                @change="setBlockType($event.target.value)"
                class="rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs font-medium text-gray-700 dark:text-gray-200 py-1.5 pl-2.5 pr-7 focus:ring-indigo-500 focus:border-indigo-500"
            >
                <option value="paragraph">Paragraph</option>
                <option value="h1">Heading 1</option>
                <option value="h2">Heading 2</option>
                <option value="h3">Heading 3</option>
                <option value="h4">Heading 4</option>
                <option value="h5">Heading 5</option>
                <option value="h6">Heading 6</option>
            </select>

            <div class="h-5 w-px bg-gray-300 dark:bg-gray-700 mx-1 flex-shrink-0"></div>

            <!-- Inline Styles -->
            <button
                type="button"
                @click="editor.chain().focus().toggleBold().run()"
                :class="[editor.isActive('bold') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs font-bold transition flex-shrink-0"
                title="Bold (Ctrl+B)"
            >
                B
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleItalic().run()"
                :class="[editor.isActive('italic') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs italic transition flex-shrink-0 font-serif"
                title="Italic (Ctrl+I)"
            >
                I
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleUnderline().run()"
                :class="[editor.isActive('underline') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs underline transition flex-shrink-0"
                title="Underline (Ctrl+U)"
            >
                U
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleStrike().run()"
                :class="[editor.isActive('strike') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs line-through transition flex-shrink-0"
                title="Strikethrough"
            >
                S
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleHighlight().run()"
                :class="[editor.isActive('highlight') ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs transition flex-shrink-0 font-semibold"
                title="Highlight"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                </svg>
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleCode().run()"
                :class="[editor.isActive('code') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 font-mono text-xs transition flex-shrink-0"
                title="Inline Code"
            >
                &lt;/&gt;
            </button>

            <!-- Text Color Button -->
            <div class="relative flex-shrink-0">
                <button
                    type="button"
                    @click="showColorPicker = !showColorPicker"
                    class="rounded p-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800 transition flex items-center gap-1"
                    title="Text Color"
                >
                    <span class="font-bold underline decoration-indigo-500 decoration-2">A</span>
                </button>
                <div
                    v-if="showColorPicker"
                    class="absolute top-full left-0 mt-2 p-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg z-20 flex flex-wrap gap-1.5 w-36"
                >
                    <button
                        v-for="color in ['#000000', '#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#6366f1', '#8b5cf6', '#ec4899', '#6b7280']"
                        :key="color"
                        type="button"
                        @click="applyColor(color)"
                        :style="{ backgroundColor: color }"
                        class="w-5 h-5 rounded-full border border-gray-300 dark:border-gray-600 shadow-sm hover:scale-110 transition"
                    ></button>
                    <button
                        type="button"
                        @click="applyColor(null)"
                        class="w-full mt-1.5 text-[10px] text-gray-600 dark:text-gray-300 underline text-center"
                    >
                        Reset color
                    </button>
                </div>
            </div>

            <div class="h-5 w-px bg-gray-300 dark:bg-gray-700 mx-1 flex-shrink-0"></div>

            <!-- Alignment -->
            <button
                type="button"
                @click="editor.chain().focus().setTextAlign('left').run()"
                :class="[editor.isActive({ textAlign: 'left' }) ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs transition flex-shrink-0"
                title="Align Left"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h16" />
                </svg>
            </button>

            <button
                type="button"
                @click="editor.chain().focus().setTextAlign('center').run()"
                :class="[editor.isActive({ textAlign: 'center' }) ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs transition flex-shrink-0"
                title="Align Center"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M4 18h16" />
                </svg>
            </button>

            <button
                type="button"
                @click="editor.chain().focus().setTextAlign('right').run()"
                :class="[editor.isActive({ textAlign: 'right' }) ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs transition flex-shrink-0"
                title="Align Right"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M4 18h16" />
                </svg>
            </button>

            <button
                type="button"
                @click="editor.chain().focus().setTextAlign('justify').run()"
                :class="[editor.isActive({ textAlign: 'justify' }) ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs transition flex-shrink-0"
                title="Justify"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <div class="h-5 w-px bg-gray-300 dark:bg-gray-700 mx-1 flex-shrink-0"></div>

            <!-- Lists and Structure -->
            <button
                type="button"
                @click="editor.chain().focus().toggleBulletList().run()"
                :class="[editor.isActive('bulletList') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs transition flex-shrink-0 font-bold"
                title="Bullet List"
            >
                • List
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleOrderedList().run()"
                :class="[editor.isActive('orderedList') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs transition flex-shrink-0 font-semibold"
                title="Numbered List"
            >
                1. List
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleTaskList().run()"
                :class="[editor.isActive('taskList') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs transition flex-shrink-0"
                title="Task List"
            >
                ☑ Tasks
            </button>

            <button
                type="button"
                @click="editor.chain().focus().toggleBlockquote().run()"
                :class="[editor.isActive('blockquote') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs font-serif italic transition flex-shrink-0"
                title="Blockquote"
            >
                “ Quote
            </button>

            <button
                type="button"
                @click="editor.chain().focus().setHorizontalRule().run()"
                class="rounded p-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800 transition flex-shrink-0 font-mono"
                title="Horizontal Rule"
            >
                ― Divider
            </button>

            <!-- Code block & language select -->
            <button
                type="button"
                @click="editor.chain().focus().toggleCodeBlock().run()"
                :class="[editor.isActive('codeBlock') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs font-mono transition flex-shrink-0"
                title="Code Block"
            >
                { } Code
            </button>

            <select
                v-if="editor.isActive('codeBlock')"
                :value="currentCodeLanguage"
                @change="setCodeLanguage($event.target.value)"
                class="rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-[11px] text-gray-700 dark:text-gray-200 py-1 pl-2 pr-6 flex-shrink-0"
            >
                <option v-for="l in codeLanguages" :key="l.value" :value="l.value">
                    {{ l.label }}
                </option>
            </select>

            <div class="h-5 w-px bg-gray-300 dark:bg-gray-700 mx-1 flex-shrink-0"></div>

            <!-- Table Controls -->
            <div class="flex items-center gap-1 flex-shrink-0">
                <button
                    type="button"
                    @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()"
                    class="rounded p-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800 transition flex-shrink-0"
                    title="Insert Table"
                >
                    ▦ Table
                </button>

                <template v-if="editor.isActive('table')">
                    <button
                        type="button"
                        @click="editor.chain().focus().addRowAfter().run()"
                        class="rounded p-1 text-[10px] bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200"
                        title="Add Row Below"
                    >
                        +Row
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().deleteRow().run()"
                        class="rounded p-1 text-[10px] bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 hover:bg-red-100"
                        title="Delete Row"
                    >
                        -Row
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().addColumnAfter().run()"
                        class="rounded p-1 text-[10px] bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200"
                        title="Add Column Right"
                    >
                        +Col
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().deleteColumn().run()"
                        class="rounded p-1 text-[10px] bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 hover:bg-red-100"
                        title="Delete Column"
                    >
                        -Col
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().deleteTable().run()"
                        class="rounded p-1 text-[10px] bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300 hover:bg-red-200"
                        title="Delete Table"
                    >
                        ✕ Table
                    </button>
                </template>
            </div>

            <div class="h-5 w-px bg-gray-300 dark:bg-gray-700 mx-1 flex-shrink-0"></div>

            <!-- Media / Inserts -->
            <button
                type="button"
                @click="openLinkModal"
                :class="[editor.isActive('link') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800']"
                class="rounded p-1.5 text-xs transition flex-shrink-0 flex items-center gap-1"
                title="Link"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
                <span>Link</span>
            </button>

            <button
                type="button"
                @click="openImageModal"
                class="rounded p-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800 transition flex-shrink-0 flex items-center gap-1"
                title="Insert Image"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>Image</span>
            </button>

            <button
                type="button"
                @click="openYoutubeModal"
                class="rounded p-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800 transition flex-shrink-0 flex items-center gap-1"
                title="Embed YouTube Video"
            >
                <svg class="w-3.5 h-3.5 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z" />
                </svg>
                <span>YouTube</span>
            </button>

            <div class="h-5 w-px bg-gray-300 dark:bg-gray-700 mx-1 flex-shrink-0"></div>

            <!-- Undo / Redo -->
            <button
                type="button"
                @click="editor.chain().focus().undo().run()"
                :disabled="!editor.can().undo()"
                class="rounded p-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800 disabled:opacity-30 transition flex-shrink-0"
                title="Undo (Ctrl+Z)"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v2m0 0l-4-4m4 4l4-4" />
                </svg>
            </button>

            <button
                type="button"
                @click="editor.chain().focus().redo().run()"
                :disabled="!editor.can().redo()"
                class="rounded p-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800 disabled:opacity-30 transition flex-shrink-0"
                title="Redo (Ctrl+Y)"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a5 5 0 00-5 5v2m0 0l4-4m-4 4l-4-4" />
                </svg>
            </button>
        </div>

        <!-- BUBBLE MENU (On text selection) -->
        <bubble-menu
            v-if="editor"
            :editor="editor"
            :tippy-options="{ duration: 120, zIndex: 30 }"
            class="flex items-center gap-1 rounded-xl border border-gray-200 bg-white p-1 shadow-xl dark:border-gray-700 dark:bg-gray-800"
        >
            <button
                type="button"
                @click="editor.chain().focus().toggleBold().run()"
                :class="[editor.isActive('bold') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700']"
                class="rounded px-2 py-1 text-xs font-bold"
            >
                B
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleItalic().run()"
                :class="[editor.isActive('italic') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700']"
                class="rounded px-2 py-1 text-xs italic font-serif"
            >
                I
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleUnderline().run()"
                :class="[editor.isActive('underline') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700']"
                class="rounded px-2 py-1 text-xs underline"
            >
                U
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleStrike().run()"
                :class="[editor.isActive('strike') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700']"
                class="rounded px-2 py-1 text-xs line-through"
            >
                S
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleHighlight().run()"
                :class="[editor.isActive('highlight') ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700']"
                class="rounded px-2 py-1 text-xs font-semibold"
            >
                Highlight
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleCode().run()"
                :class="[editor.isActive('code') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700']"
                class="rounded px-2 py-1 text-xs font-mono"
            >
                &lt;/&gt;
            </button>
            <button
                type="button"
                @click="openLinkModal"
                :class="[editor.isActive('link') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700']"
                class="rounded px-2 py-1 text-xs"
            >
                Link
            </button>
        </bubble-menu>

        <!-- SLASH COMMAND MENU (When typing /) -->
        <div
            v-if="slashMenu.isOpen && filteredSlashCommands.length > 0"
            :style="{
                position: 'fixed',
                top: `${slashMenu.coords.top}px`,
                left: `${slashMenu.coords.left}px`,
                zIndex: 50,
            }"
            class="slash-menu-popup max-h-72 w-64 overflow-y-auto rounded-xl border border-gray-200 bg-white p-1.5 shadow-2xl dark:border-gray-700 dark:bg-gray-800"
        >
            <div class="px-2 py-1 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">
                Insert Block
            </div>
            <button
                v-for="(item, idx) in filteredSlashCommands"
                :key="item.title"
                type="button"
                @click="executeSlashCommand(item)"
                :class="[
                    idx === slashMenu.selectedIndex
                        ? 'bg-indigo-50 text-indigo-900 dark:bg-indigo-950/70 dark:text-indigo-200'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-750',
                ]"
                class="flex w-full items-center gap-3 rounded-lg px-2.5 py-1.5 text-left text-xs transition"
            >
                <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded border border-gray-200 dark:border-gray-700 text-xs font-mono">
                    {{ item.icon }}
                </span>
                <div class="truncate">
                    <p class="font-medium truncate">{{ item.title }}</p>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate">{{ item.description }}</p>
                </div>
            </button>
        </div>

        <!-- EDITOR CONTENT AREA -->
        <div class="relative flex-1 cursor-text bg-white dark:bg-gray-900" @click="editor && editor.chain().focus()">
            <editor-content :editor="editor" />
        </div>

        <!-- FOOTER STATUS BAR (Word and Character Count) -->
        <div
            v-if="editor"
            class="border-t border-gray-100 dark:border-gray-800/80 px-4 py-2 bg-gray-50/50 dark:bg-gray-900/50 flex items-center justify-between text-xs text-gray-400"
        >
            <div class="flex items-center gap-3">
                <span>{{ editor.storage.characterCount.words() }} words</span>
                <span>•</span>
                <span>{{ editor.storage.characterCount.characters() }} characters</span>
            </div>
            <div class="text-[11px] text-gray-400">
                Type <kbd class="px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-800 font-mono text-[10px]">/</kbd> for block commands
            </div>
        </div>

        <!-- LINK MODAL -->
        <div
            v-if="showLinkModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-800">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Insert or Edit Link</h3>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">URL Address</label>
                        <input
                            type="text"
                            v-model="linkUrl"
                            placeholder="https://example.com"
                            class="mt-1 block w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            @keydown.enter.prevent="saveLink"
                            autofocus
                        />
                    </div>
                    <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="linkOpenInNewTab"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span>Open link in new tab</span>
                    </label>
                </div>
                <div class="mt-6 flex items-center justify-between">
                    <button
                        v-if="editor.isActive('link')"
                        type="button"
                        @click="removeLink"
                        class="text-xs font-semibold text-red-600 hover:text-red-700 dark:text-red-400"
                    >
                        Remove Link
                    </button>
                    <span v-else></span>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="showLinkModal = false"
                            class="rounded-xl px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="saveLink"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-500"
                        >
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- IMAGE INSERT MODAL -->
        <div
            v-if="showImageModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-800">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Insert Image</h3>

                <div class="mt-4 space-y-4">
                    <!-- File upload / drop area -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Upload Local Image</label>
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            @change="handleImageFileSelect"
                            class="mt-1 block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-950 dark:file:text-indigo-300"
                        />
                        <p v-if="isUploadingImage" class="mt-1 text-xs text-indigo-600">Uploading & processing WebP image...</p>
                        <p v-if="imageUploadError" class="mt-1 text-xs text-red-600">{{ imageUploadError }}</p>
                    </div>

                    <!-- Image URL -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Or Image URL</label>
                        <input
                            type="url"
                            v-model="imageUrl"
                            placeholder="https://images.unsplash.com/..."
                            class="mt-1 block w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <!-- Alt Text & Caption -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Alt Text (Accessibility)</label>
                            <input
                                type="text"
                                v-model="imageAlt"
                                placeholder="Describe the image..."
                                class="mt-1 block w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Caption (Optional)</label>
                            <input
                                type="text"
                                v-model="imageCaption"
                                placeholder="Photo caption..."
                                class="mt-1 block w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Alignment & Width -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Alignment</label>
                            <div class="mt-1 flex rounded-xl border border-gray-200 dark:border-gray-700 p-1">
                                <button
                                    type="button"
                                    v-for="al in ['left', 'center', 'right']"
                                    :key="al"
                                    @click="imageAlign = al"
                                    :class="[imageAlign === al ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700']"
                                    class="flex-1 rounded-lg py-1 text-xs font-medium capitalize transition"
                                >
                                    {{ al }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Size / Width</label>
                            <div class="mt-1 flex rounded-xl border border-gray-200 dark:border-gray-700 p-1">
                                <button
                                    type="button"
                                    v-for="w in ['25%', '50%', '75%', '100%']"
                                    :key="w"
                                    @click="imageWidth = w"
                                    :class="[imageWidth === w ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700']"
                                    class="flex-1 rounded-lg py-1 text-xs font-medium transition"
                                >
                                    {{ w }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="showImageModal = false"
                        class="rounded-xl px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="insertImage"
                        :disabled="!imageUrl.trim() || isUploadingImage"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                    >
                        Insert Image
                    </button>
                </div>
            </div>
        </div>

        <!-- YOUTUBE MODAL -->
        <div
            v-if="showYoutubeModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-800">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Embed YouTube Video</h3>
                <div class="mt-4 space-y-3">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">YouTube Video URL</label>
                    <input
                        type="url"
                        v-model="youtubeUrl"
                        placeholder="https://www.youtube.com/watch?v=..."
                        class="mt-1 block w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @keydown.enter.prevent="insertYoutube"
                        autofocus
                    />
                    <p class="text-[11px] text-gray-400">
                        Supports youtube.com, youtu.be, and embed URLs (rendered securely with privacy-enhanced mode).
                    </p>
                </div>
                <div class="mt-6 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="showYoutubeModal = false"
                        class="rounded-xl px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="insertYoutube"
                        :disabled="!youtubeUrl.trim()"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                    >
                        Embed Video
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* ProseMirror editor styles */
.ProseMirror {
    min-height: 360px;
    outline: none;
}

.ProseMirror p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: #9ca3af;
    pointer-events: none;
    height: 0;
}

.dark .ProseMirror p.is-editor-empty:first-child::before {
    color: #6b7280;
}

/* Task lists in ProseMirror */
.ProseMirror ul[data-type="taskList"] {
    list-style: none;
    padding-left: 0;
    margin: 1.25rem 0;
}

.ProseMirror ul[data-type="taskList"] li {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin: 0.35rem 0;
}

.ProseMirror ul[data-type="taskList"] li > label {
    flex-shrink: 0;
    margin-top: 0.25rem;
    user-select: none;
}

.ProseMirror ul[data-type="taskList"] li > label input[type="checkbox"] {
    border-radius: 0.25rem;
    border-color: #d1d5db;
    color: #4f46e5;
    cursor: pointer;
}

.dark .ProseMirror ul[data-type="taskList"] li > label input[type="checkbox"] {
    border-color: #4b5563;
    background-color: #1f2937;
}

.ProseMirror ul[data-type="taskList"] li > div {
    flex: 1 1 auto;
}

/* Tables in ProseMirror */
.ProseMirror table {
    width: 100%;
    border-collapse: collapse;
    margin: 1.5rem 0;
    display: table;
    border: 1px solid #e5e7eb;
}

.dark .ProseMirror table {
    border-color: #374151;
}

.ProseMirror th {
    background-color: #f9fafb;
    font-weight: 600;
    text-align: left;
    padding: 0.75rem 1rem;
    border: 1px solid #e5e7eb;
}

.dark .ProseMirror th {
    background-color: #1f2937;
    border-color: #374151;
    color: #f3f4f6;
}

.ProseMirror td {
    padding: 0.75rem 1rem;
    border: 1px solid #e5e7eb;
    vertical-align: top;
}

.dark .ProseMirror td {
    border-color: #374151;
}

/* Code blocks in ProseMirror */
.ProseMirror pre {
    background-color: #1e1e2e;
    color: #cdd6f4;
    padding: 1.25rem;
    border-radius: 0.75rem;
    overflow-x: auto;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.9em;
    line-height: 1.6;
}

.ProseMirror pre code {
    background: transparent;
    padding: 0;
    color: inherit;
    font-size: inherit;
}

/* Image alignment & caption */
.ProseMirror img {
    border-radius: 0.75rem;
    max-width: 100%;
    height: auto;
    margin: 1.5rem 0;
}

.ProseMirror img[data-align="center"] {
    display: block;
    margin-left: auto;
    margin-right: auto;
}

.ProseMirror img[data-align="left"] {
    float: left;
    margin-right: 1.5rem;
    margin-bottom: 1rem;
    margin-top: 0.5rem;
}

.ProseMirror img[data-align="right"] {
    float: right;
    margin-left: 1.5rem;
    margin-bottom: 1rem;
    margin-top: 0.5rem;
}

/* YouTube embeds */
.ProseMirror iframe {
    width: 100%;
    aspect-ratio: 16 / 9;
    border-radius: 0.75rem;
    margin: 1.5rem 0;
}
</style>
