import { ref, computed } from 'vue';
import type { Chapter, ChapterResponse, PageSpread, PageContentItem, ReadingView, ApiFetchFn, InlineImage } from '../types';
import { apiFetch } from '@/composables/ApiFetch';

const CHARS_PER_LINE = 55;
const LINES_PER_FULL_PAGE = 28;
const FIRST_PAGE_LINES = 14;
const CHARS_PER_FULL_PAGE = CHARS_PER_LINE * LINES_PER_FULL_PAGE;
const CHARS_FIRST_PAGE = CHARS_PER_LINE * FIRST_PAGE_LINES;
const IMAGE_CHAR_EQUIVALENT = 400;

export function useChapterPagination() {
    const requestApiFetch = apiFetch as ApiFetchFn;

    const currentChapterNumber = ref(0);
    const currentChapter = ref<Chapter | null>(null);
    const nextChapterData = ref<Chapter | null>(null);
    const totalChapters = ref(0);
    const isLoadingChapter = ref(false);
    const isGeneratingChapter = ref(false);
    const chapterError = ref<string | null>(null);
    const nextChapterPrompt = ref('');
    const isFinalChapter = ref(false);
    const readingView = ref<ReadingView>('title');
    const isPageFlipping = ref(false);
    const isTitlePageFading = ref(false);
    const currentSpreadIndex = ref(0);
    const lastChapterEndedOnLeft = ref(false);

    const extractErrorMessage = (value: unknown): string | null => {
        if (value instanceof Error) {
            return value.message;
        }
        if (typeof value === 'string') {
            return value;
        }
        if (value && typeof value === 'object' && 'message' in value) {
            const message = (value as { message?: unknown }).message;
            return typeof message === 'string' ? message : null;
        }
        return null;
    };

    const calculateChapterPages = (chapter: Chapter | null): PageContentItem[][] => {
        if (!chapter?.body) {
            return [];
        }

        const body = chapter.body;
        const paragraphs = body.split('\n\n').filter(p => p.trim());

        if (paragraphs.length === 0) {
            return [];
        }

        const inlineImages = chapter.inline_images || [];
        const imagesByParagraph = new Map<number, InlineImage>();
        for (const img of inlineImages) {
            imagesByParagraph.set(img.paragraph_index, img);
        }

        const contentItems: PageContentItem[] = [];
        for (let i = 0; i < paragraphs.length; i++) {
            contentItems.push({
                type: 'paragraph',
                content: paragraphs[i],
            });
            
            const imageForParagraph = imagesByParagraph.get(i);
            if (imageForParagraph) {
                contentItems.push({
                    type: 'image',
                    content: imageForParagraph.prompt,
                    imageUrl: imageForParagraph.url,
                });
            }
        }

        const pages: PageContentItem[][] = [];
        let currentPage: PageContentItem[] = [];
        let currentPageChars = 0;
        let pageIndex = 0;

        for (const item of contentItems) {
            const maxChars = pageIndex === 0 ? CHARS_FIRST_PAGE : CHARS_PER_FULL_PAGE;
            const itemChars = item.type === 'image' 
                ? IMAGE_CHAR_EQUIVALENT 
                : item.content.length + 20;

            if (currentPageChars + itemChars > maxChars && currentPage.length > 0) {
                pages.push(currentPage);
                currentPage = [item];
                currentPageChars = itemChars;
                pageIndex++;
            } else {
                currentPage.push(item);
                currentPageChars += itemChars;
            }
        }

        if (currentPage.length > 0) {
            pages.push(currentPage);
        }

        return pages;
    };

    const chapterPages = computed((): PageContentItem[][] => {
        return calculateChapterPages(currentChapter.value);
    });

    const chapterSpreads = computed((): PageSpread[] => {
        const pages = chapterPages.value;
        if (pages.length === 0) {
            return [];
        }

        const spreads: PageSpread[] = [];

        spreads.push({
            leftContent: null,
            rightContent: pages[0] || null,
            isFirstSpread: true,
            showImage: true
        });

        for (let i = 1; i < pages.length; i += 2) {
            spreads.push({
                leftContent: pages[i] || null,
                rightContent: pages[i + 1] || null,
                isFirstSpread: false,
                showImage: false
            });
        }

        return spreads;
    });

    const currentSpread = computed(() => {
        return chapterSpreads.value[currentSpreadIndex.value] || null;
    });

    const hasNextSpread = computed(() => {
        return currentSpreadIndex.value < chapterSpreads.value.length - 1;
    });

    const hasPrevSpread = computed(() => {
        return currentSpreadIndex.value > 0;
    });

    const totalSpreads = computed(() => {
        return chapterSpreads.value.length;
    });

    const chapterEndsOnLeft = computed(() => {
        // When in create-chapter view, use the preserved value
        if (readingView.value === 'create-chapter') {
            return lastChapterEndedOnLeft.value;
        }
        const spreads = chapterSpreads.value;
        if (spreads.length === 0) {
            return false;
        }
        const lastSpread = spreads[spreads.length - 1];
        return lastSpread.rightContent === null || lastSpread.rightContent === undefined;
    });

    const hasNextChapter = computed(() => {
        if (readingView.value === 'create-chapter') {
            return false;
        }
        return totalChapters.value > 0 && currentChapterNumber.value < totalChapters.value;
    });

    const isOnLastSpread = computed(() => {
        return currentSpreadIndex.value === chapterSpreads.value.length - 1;
    });

    const nextChapterFirstPage = computed((): PageContentItem[] | null => {
        if (!nextChapterData.value) {
            return null;
        }
        const pages = calculateChapterPages(nextChapterData.value);
        return pages[0] || null;
    });

    const shouldShowNextChapterOnRight = computed(() => {
        return isOnLastSpread.value && 
               chapterEndsOnLeft.value && 
               hasNextChapter.value && 
               nextChapterData.value !== null;
    });

    const loadNextChapterData = async (bookId: string, nextChapterNumber: number): Promise<void> => {
        try {
            const { data, error } = await requestApiFetch(
                `/api/books/${bookId}/chapters/${nextChapterNumber}`,
                'GET'
            );

            if (!error && data) {
                const response = data as ChapterResponse;
                if (response.chapter) {
                    nextChapterData.value = response.chapter;
                } else {
                    nextChapterData.value = null;
                }
            } else {
                nextChapterData.value = null;
            }
        } catch {
            nextChapterData.value = null;
        }
    };

    const loadChapter = async (bookId: string, chapterNumber: number): Promise<void> => {
        if (!bookId || isLoadingChapter.value) {
            return;
        }

        isLoadingChapter.value = true;
        chapterError.value = null;
        nextChapterData.value = null;

        try {
            const { data, error } = await requestApiFetch(
                `/api/books/${bookId}/chapters/${chapterNumber}`,
                'GET'
            );

            if (error) {
                chapterError.value = extractErrorMessage(error) ?? 'Could not load chapter.';
                return;
            }

            const response = data as ChapterResponse;
            totalChapters.value = response.total_chapters;

            if (response.chapter) {
                currentChapter.value = response.chapter;
                currentChapterNumber.value = chapterNumber;
                currentSpreadIndex.value = 0;
                readingView.value = 'chapter-image';
                
                // Pre-fetch next chapter if available
                if (chapterNumber < response.total_chapters) {
                    loadNextChapterData(bookId, chapterNumber + 1);
                }
            } else {
                currentChapter.value = null;
                currentChapterNumber.value = chapterNumber;
                readingView.value = 'create-chapter';
            }
        } catch (err) {
            chapterError.value = extractErrorMessage(err) ?? 'An error occurred loading the chapter.';
        } finally {
            isLoadingChapter.value = false;
        }
    };

    const generateNextChapter = async (bookId: string): Promise<void> => {
        if (!bookId || isGeneratingChapter.value) {
            return;
        }

        isGeneratingChapter.value = true;
        chapterError.value = null;

        try {
            const { data, error } = await requestApiFetch(
                `/api/books/${bookId}/chapters/generate`,
                'POST',
                {
                    user_prompt: nextChapterPrompt.value.trim() || null,
                    final_chapter: isFinalChapter.value,
                }
            );

            if (error) {
                chapterError.value = extractErrorMessage(error) ?? 'Could not generate chapter.';
                return;
            }

            const response = data as ChapterResponse;

            if (response.chapter) {
                currentChapter.value = response.chapter;
                currentChapterNumber.value = response.chapter.sort;
                totalChapters.value = response.total_chapters;
                nextChapterPrompt.value = '';
                isFinalChapter.value = false;
                currentSpreadIndex.value = 0;
                readingView.value = 'chapter-image';
            }
        } catch (err) {
            chapterError.value = extractErrorMessage(err) ?? 'An error occurred generating the chapter.';
        } finally {
            isGeneratingChapter.value = false;
        }
    };

    const goToChapter1 = (
        bookId: string,
        scheduleTimeout: (callback: () => void, delay: number) => void
    ): void => {
        if (isTitlePageFading.value || isLoadingChapter.value) {
            return;
        }
        isTitlePageFading.value = true;

        scheduleTimeout(() => {
            loadChapter(bookId, 1);
            isTitlePageFading.value = false;
        }, 500);
    };

    const goToNextChapter = (bookId: string): void => {
        if (isLoadingChapter.value || isPageFlipping.value) {
            return;
        }

        if (hasNextSpread.value) {
            currentSpreadIndex.value++;
        } else if (shouldShowNextChapterOnRight.value && nextChapterData.value) {
            // When showing next chapter preview on right, advance to that chapter
            // but start from spread index 1 (skip the title spread since we already showed the first page)
            currentChapter.value = nextChapterData.value;
            currentChapterNumber.value = nextChapterData.value.sort;
            nextChapterData.value = null;
            currentSpreadIndex.value = 1;
            readingView.value = 'chapter-content';
            
            // Pre-fetch the new next chapter
            if (currentChapterNumber.value < totalChapters.value) {
                loadNextChapterData(bookId, currentChapterNumber.value + 1);
            }
        } else {
            const nextNumber = currentChapterNumber.value + 1;

            if (nextNumber <= totalChapters.value) {
                loadChapter(bookId, nextNumber);
            } else {
                // Preserve whether the chapter ended on left before clearing
                lastChapterEndedOnLeft.value = chapterEndsOnLeft.value;
                currentChapter.value = null;
                currentChapterNumber.value = nextNumber;
                currentSpreadIndex.value = 0;
                readingView.value = 'create-chapter';
            }
        }
    };

    const goToPreviousChapter = async (bookId: string): Promise<void> => {
        if (isLoadingChapter.value || isPageFlipping.value) {
            return;
        }

        if (hasPrevSpread.value) {
            currentSpreadIndex.value--;
        } else if (currentChapterNumber.value > 1) {
            // Load the previous chapter and go to its last spread
            const prevChapterNumber = currentChapterNumber.value - 1;
            isLoadingChapter.value = true;
            chapterError.value = null;
            nextChapterData.value = null;

            try {
                const { data, error } = await requestApiFetch(
                    `/api/books/${bookId}/chapters/${prevChapterNumber}`,
                    'GET'
                );

                if (error) {
                    chapterError.value = extractErrorMessage(error) ?? 'Could not load chapter.';
                    return;
                }

                const response = data as ChapterResponse;
                totalChapters.value = response.total_chapters;

                if (response.chapter) {
                    currentChapter.value = response.chapter;
                    currentChapterNumber.value = prevChapterNumber;
                    
                    // Calculate spreads for this chapter to find the last one
                    const pages = calculateChapterPages(response.chapter);
                    const spreads: PageSpread[] = [];
                    
                    spreads.push({
                        leftContent: null,
                        rightContent: pages[0] || null,
                        isFirstSpread: true,
                        showImage: true
                    });

                    for (let i = 1; i < pages.length; i += 2) {
                        spreads.push({
                            leftContent: pages[i] || null,
                            rightContent: pages[i + 1] || null,
                            isFirstSpread: false,
                            showImage: false
                        });
                    }
                    
                    // Set to the last spread of the previous chapter
                    currentSpreadIndex.value = spreads.length - 1;
                    readingView.value = 'chapter-content';
                    
                    // Pre-fetch next chapter (which is the chapter we just came from)
                    if (prevChapterNumber < response.total_chapters) {
                        loadNextChapterData(bookId, prevChapterNumber + 1);
                    }
                } else {
                    currentChapter.value = null;
                    currentChapterNumber.value = prevChapterNumber;
                    currentSpreadIndex.value = 0;
                    readingView.value = 'create-chapter';
                }
            } catch (err) {
                chapterError.value = extractErrorMessage(err) ?? 'An error occurred loading the chapter.';
            } finally {
                isLoadingChapter.value = false;
            }
        } else if (currentChapterNumber.value === 1) {
            currentChapter.value = null;
            currentChapterNumber.value = 0;
            currentSpreadIndex.value = 0;
            readingView.value = 'title';
        }
    };

    const goBackToTitlePage = (): void => {
        currentChapter.value = null;
        currentChapterNumber.value = 0;
        readingView.value = 'title';
    };

    const goToTableOfContents = (): void => {
        readingView.value = 'toc';
    };

    const jumpToChapter = (bookId: string, chapterNumber: number): void => {
        if (isLoadingChapter.value) {
            return;
        }
        loadChapter(bookId, chapterNumber);
    };

    const resetChapterState = (): void => {
        currentChapterNumber.value = 0;
        currentChapter.value = null;
        nextChapterData.value = null;
        totalChapters.value = 0;
        isLoadingChapter.value = false;
        isGeneratingChapter.value = false;
        chapterError.value = null;
        nextChapterPrompt.value = '';
        isFinalChapter.value = false;
        currentSpreadIndex.value = 0;
        readingView.value = 'title';
        isPageFlipping.value = false;
        isTitlePageFading.value = false;
        lastChapterEndedOnLeft.value = false;
    };

    return {
        currentChapterNumber,
        currentChapter,
        nextChapterData,
        totalChapters,
        isLoadingChapter,
        isGeneratingChapter,
        chapterError,
        nextChapterPrompt,
        isFinalChapter,
        readingView,
        isPageFlipping,
        isTitlePageFading,
        currentSpreadIndex,
        chapterPages,
        chapterSpreads,
        currentSpread,
        hasNextSpread,
        hasPrevSpread,
        totalSpreads,
        chapterEndsOnLeft,
        hasNextChapter,
        isOnLastSpread,
        nextChapterFirstPage,
        shouldShowNextChapterOnRight,
        loadChapter,
        generateNextChapter,
        goToChapter1,
        goToNextChapter,
        goToPreviousChapter,
        goBackToTitlePage,
        goToTableOfContents,
        jumpToChapter,
        resetChapterState,
    };
}

