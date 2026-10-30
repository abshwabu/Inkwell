import { Image as BaseImage } from '@tiptap/extension-image';

export const CustomImage = BaseImage.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            align: {
                default: 'center',
                renderHTML: (attributes) => {
                    return {
                        'data-align': attributes.align || 'center',
                    };
                },
                parseHTML: (element) => {
                    return element.getAttribute('data-align') || 'center';
                },
            },
            width: {
                default: '100%',
                renderHTML: (attributes) => {
                    if (!attributes.width) return {};
                    return {
                        style: `width: ${attributes.width}`,
                    };
                },
                parseHTML: (element) => {
                    return element.style.width || element.getAttribute('width') || '100%';
                },
            },
            caption: {
                default: null,
                renderHTML: (attributes) => {
                    if (!attributes.caption) return {};
                    return {
                        'data-caption': attributes.caption,
                    };
                },
                parseHTML: (element) => {
                    return element.getAttribute('data-caption') || null;
                },
            },
        };
    },
});

export default CustomImage;
