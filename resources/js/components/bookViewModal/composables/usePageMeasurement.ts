import { ref, type Ref } from 'vue';
import type { PageContentItem } from '../types';

interface PageDimensions {
    modalWidth: number;
    modalHeight: number;
    isSinglePage: boolean;
}

/**
 * Composable that provides DOM-based measurement for book page pagination.
 *
 * Instead of estimating how much text fits on each page using character counts,
 * this creates a temporary off-screen container matching the exact page styling,
 * renders each content item into it, and measures actual pixel heights.
 *
 * The measured heights are then used to distribute content items across pages
 * so that no page overflows and no page is needlessly underfilled.
 */
export function usePageMeasurement() {
    const dimensions: Ref<PageDimensions> = ref({
        modalWidth: 0,
        modalHeight: 0,
        isSinglePage: false,
    });

    let resizeObserver: ResizeObserver | null = null;
    let observedElement: HTMLElement | null = null;

    /**
     * Get the content width available for text within a single book page.
     * Each page uses px-16 (64px each side = 128px total horizontal padding).
     */
    function getContentWidth(): number {
        const pageWidth = dimensions.value.isSinglePage
            ? dimensions.value.modalWidth
            : dimensions.value.modalWidth / 2;

        return Math.max(pageWidth - 128, 100);
    }

    /**
     * Calculate available content height for the first page of a chapter.
     *
     * First-page layout (from BookChapterContent.vue first-spread template):
     *   - Outer flex column: pt-6 (24px) + pb-6 (24px)
     *   - Header image section: mt-10 (40px) + aspect-16/7 image + mb-6 (24px)
     *   - Title section: ~90px (chapter label, title text, decorative divider)
     *   - Content area: flex-1 with pb-8 (32px)
     *   - Footer page number: absolute bottom-6 (~24px overlap)
     */
    function getFirstPageHeight(): number {
        const contentWidth = getContentWidth();
        const modalHeight = dimensions.value.modalHeight;

        const outerPadding = 48;
        const headerImageHeight = contentWidth * (7 / 16);
        const headerSection = 40 + headerImageHeight + 24;
        const titleSection = 90;
        const contentBottomPadding = 32;
        const footerOverlap = 24;

        return Math.max(
            modalHeight - outerPadding - headerSection - titleSection - contentBottomPadding - footerOverlap,
            80,
        );
    }

    /**
     * Calculate available content height for subsequent (non-first) pages.
     *
     * Subsequent-page layout (from BookChapterContent.vue and BookLeftPage.vue):
     *   - Container: pt-24 (96px top) + pb-12 (48px bottom) = 144px total padding
     */
    function getFullPageHeight(): number {
        return Math.max(dimensions.value.modalHeight - 144, 80);
    }

    /**
     * Measure the rendered heights of content items using an off-screen DOM container.
     *
     * Creates temporary elements that match the actual page CSS classes, appends them all
     * to an off-screen container at the correct content width, then batch-reads heights
     * (single browser reflow for the whole batch).
     */
    function measureItemHeights(items: PageContentItem[]): number[] {
        const contentWidth = getContentWidth();

        if (contentWidth <= 0 || items.length === 0) {
            return items.map(() => 40);
        }

        const container = document.createElement('div');
        container.style.cssText = `
            position: absolute;
            left: -9999px;
            top: -9999px;
            width: ${contentWidth}px;
            visibility: hidden;
            pointer-events: none;
        `;
        container.className = 'prose prose-amber prose-lg max-w-none text-amber-950';
        document.body.appendChild(container);

        const elements: HTMLElement[] = [];

        for (const item of items) {
            if (item.type === 'paragraph') {
                const p = document.createElement('p');
                p.className = 'mb-5 font-serif text-lg leading-relaxed';
                p.textContent = item.content;
                container.appendChild(p);
                elements.push(p);
            } else {
                const figure = document.createElement('figure');
                figure.className = 'my-6';
                const placeholder = document.createElement('div');
                placeholder.style.width = '100%';
                placeholder.style.aspectRatio = '16 / 9';
                figure.appendChild(placeholder);
                container.appendChild(figure);
                elements.push(figure);
            }
        }

        const heights: number[] = [];

        for (const el of elements) {
            const style = window.getComputedStyle(el);
            const marginTop = parseFloat(style.marginTop) || 0;
            const marginBottom = parseFloat(style.marginBottom) || 0;
            heights.push(el.offsetHeight + marginTop + marginBottom);
        }

        document.body.removeChild(container);

        return heights;
    }

    /**
     * Distribute content items across pages using measured pixel heights.
     */
    function paginateByHeight(
        items: PageContentItem[],
        itemHeights: number[],
    ): PageContentItem[][] {
        if (items.length === 0) {
            return [];
        }

        const firstPageHeight = getFirstPageHeight();
        const fullPageHeight = getFullPageHeight();

        const pages: PageContentItem[][] = [];
        let currentPage: PageContentItem[] = [];
        let usedHeight = 0;
        let pageIndex = 0;

        for (let i = 0; i < items.length; i++) {
            const maxHeight = pageIndex === 0 ? firstPageHeight : fullPageHeight;
            const itemHeight = itemHeights[i] || 40;

            if (usedHeight + itemHeight > maxHeight && currentPage.length > 0) {
                pages.push(currentPage);
                currentPage = [items[i]];
                usedHeight = itemHeight;
                pageIndex++;
            } else {
                currentPage.push(items[i]);
                usedHeight += itemHeight;
            }
        }

        if (currentPage.length > 0) {
            pages.push(currentPage);
        }

        return pages;
    }

    /**
     * Main entry point: measure content items and split them into pages.
     * Falls back to character-count estimation when DOM dimensions are unavailable.
     */
    function measureAndPaginate(items: PageContentItem[]): PageContentItem[][] {
        if (dimensions.value.modalWidth === 0 || dimensions.value.modalHeight === 0) {
            return fallbackPaginate(items);
        }

        const heights = measureItemHeights(items);
        return paginateByHeight(items, heights);
    }

    /**
     * Fallback pagination using character estimation (before modal is measured).
     */
    function fallbackPaginate(items: PageContentItem[]): PageContentItem[][] {
        const CHARS_FIRST_PAGE = 55 * 14;
        const CHARS_FULL_PAGE = 55 * 28;
        const IMAGE_CHARS = 400;

        const pages: PageContentItem[][] = [];
        let currentPage: PageContentItem[] = [];
        let chars = 0;
        let pageIndex = 0;

        for (const item of items) {
            const maxChars = pageIndex === 0 ? CHARS_FIRST_PAGE : CHARS_FULL_PAGE;
            const itemChars = item.type === 'image' ? IMAGE_CHARS : item.content.length + 20;

            if (chars + itemChars > maxChars && currentPage.length > 0) {
                pages.push(currentPage);
                currentPage = [item];
                chars = itemChars;
                pageIndex++;
            } else {
                currentPage.push(item);
                chars += itemChars;
            }
        }

        if (currentPage.length > 0) {
            pages.push(currentPage);
        }

        return pages;
    }

    /**
     * Attach a ResizeObserver to the given element and track its dimensions.
     */
    function setContainerRef(el: HTMLElement | null, isSinglePage: boolean = false): void {
        if (resizeObserver && observedElement) {
            resizeObserver.unobserve(observedElement);
        }

        observedElement = el;

        if (el) {
            dimensions.value = {
                modalWidth: el.clientWidth,
                modalHeight: el.clientHeight,
                isSinglePage,
            };

            if (!resizeObserver) {
                resizeObserver = new ResizeObserver((entries) => {
                    for (const entry of entries) {
                        dimensions.value = {
                            ...dimensions.value,
                            modalWidth: entry.contentRect.width,
                            modalHeight: entry.contentRect.height,
                        };
                    }
                });
            }

            resizeObserver.observe(el);
        } else {
            dimensions.value = { modalWidth: 0, modalHeight: 0, isSinglePage: false };
        }
    }

    /**
     * Update single-page mode (called when responsive breakpoint changes).
     */
    function setSinglePageMode(isSinglePage: boolean): void {
        dimensions.value = { ...dimensions.value, isSinglePage };
    }

    /**
     * Clean up the ResizeObserver.
     */
    function destroy(): void {
        if (resizeObserver) {
            resizeObserver.disconnect();
            resizeObserver = null;
        }
        observedElement = null;
    }

    return {
        dimensions,
        measureAndPaginate,
        setContainerRef,
        setSinglePageMode,
        destroy,
    };
}
