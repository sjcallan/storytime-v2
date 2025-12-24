import { ref, computed } from 'vue';
import type { Chapter, ChapterResponse, PageSpread, PageContentItem, ReadingView, ApiFetchFn, InlineImage, ReadingHistory } from '../types';
import { apiFetch } from '@/composables/ApiFetch';

const CHARS_PER_LINE = 55;
const LINES_PER_FULL_PAGE = 28;
const FIRST_PAGE_LINES = 14;
const CHARS_PER_FULL_PAGE = CHARS_PER_LINE * LINES_PER_FULL_PAGE;
const CHARS_FIRST_PAGE = CHARS_PER_LINE * FIRST_PAGE_LINES;
const IMAGE_CHAR_EQUIVALENT = 400;

// Types for chapter broadcast events
export type ChapterCreatedPayload = {
    id: string;
    title: string | null;
    book_id: string;
    sort: number;
    status: string | null;
    created_at: string;
};

export type ChapterUpdatedPayload = {
    id: string;
    book_id: string;
    title: string | null;
    sort: number;
    status: string | null;
    body: string | null;
    summary: string | null;
    image: string | null;
    image_prompt: string | null;
    final_chapter: boolean;
    inline_images: InlineImage[] | null;
    updated_at: string;
};

export type ReadingHistoryCallback = (history: ReadingHistory) => void;

export function useChapterPagination(onReadingHistoryUpdate?: ReadingHistoryCallback) {
    const requestApiFetch = apiFetch as ApiFetchFn;

    const currentChapterNumber = ref(0);
    const currentChapter = ref<Chapter | null>(null);
    const nextChapterData = ref<Chapter | null>(null);
    const totalChapters = ref(0);
    const isLoadingChapter = ref(false);
    const isGeneratingChapter = ref(false);
    const isAwaitingChapterGeneration = ref(false);
    const pendingChapterId = ref<string | null>(null);
    const chapterError = ref<string | null>(null);
    const nextChapterPrompt = ref('');
    const suggestedIdea = ref<string | null>(null);
    const isLoadingIdea = ref(false);
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
                const isPending = imageForParagraph.status === 'pending' || !imageForParagraph.url;
                contentItems.push({
                    type: 'image',
                    content: imageForParagraph.prompt,
                    imageUrl: imageForParagraph.url,
                    imageStatus: isPending ? 'pending' : 'complete',
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

    const requestIdea = async (bookId: string): Promise<void> => {
        if (isLoadingIdea.value) {
            return;
        }

        isLoadingIdea.value = true;

        try {
            const { data, error } = await requestApiFetch(
                `/api/books/${bookId}/chapters/suggest-prompt`,
                'GET'
            );

            if (!error && data) {
                const response = data as { placeholder: string | null };
                suggestedIdea.value = response.placeholder;
            }
        } catch {
            // Silently fail
            suggestedIdea.value = null;
        } finally {
            isLoadingIdea.value = false;
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
                // Clear any previous idea when entering create-chapter view
                suggestedIdea.value = null;
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
            recordChapterAdvancement(bookId, 1);
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
            // User finished current chapter - record advancement to next chapter
            const finishedChapterId = currentChapter.value?.id;
            const nextChapterNumber = nextChapterData.value.sort;
            console.log('[ReadingHistory] Advancing chapter (preview on right):', { bookId, nextChapterNumber, finishedChapterId });
            recordChapterAdvancement(bookId, nextChapterNumber, finishedChapterId);
            
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
                // User finished current chapter - record advancement to next chapter
                const finishedChapterId = currentChapter.value?.id;
                console.log('[ReadingHistory] Advancing chapter (loading next):', { bookId, nextNumber, finishedChapterId });
                recordChapterAdvancement(bookId, nextNumber, finishedChapterId);
                
                loadChapter(bookId, nextNumber);
            } else {
                // User reached end of all chapters - record they finished the last chapter
                const finishedChapterId = currentChapter.value?.id;
                if (finishedChapterId) {
                    console.log('[ReadingHistory] Advancing chapter (end of book):', { bookId, nextNumber, finishedChapterId });
                    recordChapterAdvancement(bookId, nextNumber, finishedChapterId);
                }
                
                // Preserve whether the chapter ended on left before clearing
                lastChapterEndedOnLeft.value = chapterEndsOnLeft.value;
                currentChapter.value = null;
                currentChapterNumber.value = nextNumber;
                currentSpreadIndex.value = 0;
                readingView.value = 'create-chapter';
                // Clear any previous idea when entering create-chapter view
                suggestedIdea.value = null;
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
        isAwaitingChapterGeneration.value = false;
        pendingChapterId.value = null;
        chapterError.value = null;
        nextChapterPrompt.value = '';
        suggestedIdea.value = null;
        isLoadingIdea.value = false;
        isFinalChapter.value = false;
        currentSpreadIndex.value = 0;
        readingView.value = 'title';
        isPageFlipping.value = false;
        isTitlePageFading.value = false;
        lastChapterEndedOnLeft.value = false;
    };

    const updateChapterInlineImages = (chapterId: string, inlineImages: InlineImage[]): void => {
        // Update current chapter if it matches
        if (currentChapter.value && currentChapter.value.id === chapterId) {
            currentChapter.value = {
                ...currentChapter.value,
                inline_images: inlineImages,
            };
        }
        
        // Update next chapter data if it matches
        if (nextChapterData.value && nextChapterData.value.id === chapterId) {
            nextChapterData.value = {
                ...nextChapterData.value,
                inline_images: inlineImages,
            };
        }
    };

    // Handle chapter created event from broadcast
    const handleChapterCreated = (payload: ChapterCreatedPayload): void => {
        // A chapter was created - track it as pending if it doesn't have 'complete' status
        if (payload.status !== 'complete') {
            isAwaitingChapterGeneration.value = true;
            pendingChapterId.value = payload.id;
        }
    };

    // Handle chapter updated event from broadcast
    const handleChapterUpdated = (payload: ChapterUpdatedPayload, bookId: string): void => {
        // If this chapter just became complete, update state and load it
        if (payload.status === 'complete') {
            // Clear the awaiting state if:
            // 1. This was the specific pending chapter we were waiting for, OR
            // 2. We're awaiting chapter generation and this is the first chapter (sort === 1)
            //    (handles case where pendingChapterId wasn't set, e.g., from book open)
            if (pendingChapterId.value === payload.id || 
                (isAwaitingChapterGeneration.value && payload.sort === 1)) {
                isAwaitingChapterGeneration.value = false;
                pendingChapterId.value = null;
            }

            // Update total chapters count
            totalChapters.value = Math.max(totalChapters.value, payload.sort);

            // If we're on the title page or waiting for chapter 1, auto-navigate
            if (readingView.value === 'title' && payload.sort === 1) {
                // Convert payload to Chapter type and load it
                const chapter: Chapter = {
                    id: payload.id,
                    title: payload.title,
                    sort: payload.sort,
                    body: payload.body,
                    summary: payload.summary,
                    image: payload.image,
                    image_prompt: payload.image_prompt,
                    final_chapter: payload.final_chapter,
                    inline_images: payload.inline_images,
                };
                currentChapter.value = chapter;
                currentChapterNumber.value = payload.sort;
                currentSpreadIndex.value = 0;
                readingView.value = 'chapter-image';
                
                // Pre-fetch next chapter if available
                if (payload.sort < totalChapters.value) {
                    loadNextChapterData(bookId, payload.sort + 1);
                }
            }
            // If we're on the create-chapter view and this is the chapter we're waiting for
            else if (readingView.value === 'create-chapter' && payload.sort === currentChapterNumber.value) {
                const chapter: Chapter = {
                    id: payload.id,
                    title: payload.title,
                    sort: payload.sort,
                    body: payload.body,
                    summary: payload.summary,
                    image: payload.image,
                    image_prompt: payload.image_prompt,
                    final_chapter: payload.final_chapter,
                    inline_images: payload.inline_images,
                };
                currentChapter.value = chapter;
                currentSpreadIndex.value = 0;
                readingView.value = 'chapter-image';
                nextChapterPrompt.value = '';
                isFinalChapter.value = false;
            }
            // Update current chapter if viewing the same one
            else if (currentChapter.value && currentChapter.value.id === payload.id) {
                currentChapter.value = {
                    ...currentChapter.value,
                    title: payload.title,
                    body: payload.body,
                    summary: payload.summary,
                    image: payload.image,
                    image_prompt: payload.image_prompt,
                    final_chapter: payload.final_chapter,
                    inline_images: payload.inline_images,
                };
            }
            // Update next chapter data if it matches
            else if (nextChapterData.value && nextChapterData.value.id === payload.id) {
                nextChapterData.value = {
                    ...nextChapterData.value,
                    title: payload.title,
                    body: payload.body,
                    summary: payload.summary,
                    image: payload.image,
                    image_prompt: payload.image_prompt,
                    final_chapter: payload.final_chapter,
                    inline_images: payload.inline_images,
                };
            }
        }
    };

    // Set awaiting state (for when book has in_progress status but no chapters)
    const setAwaitingChapterGeneration = (awaiting: boolean): void => {
        isAwaitingChapterGeneration.value = awaiting;
        if (!awaiting) {
            pendingChapterId.value = null;
        }
    };

    /**
     * Record book opened - creates or updates reading history.
     * Returns the full reading history object including saved chapter number.
     */
    const recordBookOpened = async (bookId: string): Promise<ReadingHistory | null> => {
        console.log('[ReadingHistory] Recording book opened:', bookId);
        try {
            const { data, error } = await requestApiFetch(
                `/api/books/${bookId}/reading-history/open`,
                'POST',
                {} // Send empty object to ensure POST body is included
            );

            if (error) {
                console.error('[ReadingHistory] Failed to record book opened:', error);
                return null;
            }

            if (data) {
                const history = data as ReadingHistory;
                console.log('[ReadingHistory] Book opened recorded successfully:', history);
                if (onReadingHistoryUpdate) {
                    onReadingHistoryUpdate(history);
                }
                return history;
            }
        } catch (err) {
            console.error('[ReadingHistory] Exception recording book opened:', err);
        }
        return null;
    };

    /**
     * Record chapter advancement - updates reading history when user finishes a chapter.
     * Returns the updated reading history object.
     */
    const recordChapterAdvancement = async (
        bookId: string,
        chapterNumber: number,
        chapterId?: string
    ): Promise<ReadingHistory | null> => {
        console.log('[ReadingHistory] Recording chapter advancement:', { bookId, chapterNumber, chapterId });
        try {
            const { data, error } = await requestApiFetch(
                `/api/books/${bookId}/reading-history/advance`,
                'POST',
                {
                    chapter_number: chapterNumber,
                    chapter_id: chapterId || null,
                }
            );

            if (error) {
                console.error('[ReadingHistory] Failed to record chapter advancement:', error);
                return null;
            }
            
            if (data) {
                const history = data as ReadingHistory;
                console.log('[ReadingHistory] Chapter advancement recorded successfully:', history);
                if (onReadingHistoryUpdate) {
                    onReadingHistoryUpdate(history);
                }
                return history;
            }
        } catch (err) {
            console.error('[ReadingHistory] Exception recording chapter advancement:', err);
        }
        return null;
    };

    return {
        currentChapterNumber,
        currentChapter,
        nextChapterData,
        totalChapters,
        isLoadingChapter,
        isGeneratingChapter,
        isAwaitingChapterGeneration,
        pendingChapterId,
        chapterError,
        nextChapterPrompt,
        suggestedIdea,
        isLoadingIdea,
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
        updateChapterInlineImages,
        handleChapterCreated,
        handleChapterUpdated,
        setAwaitingChapterGeneration,
        recordBookOpened,
        recordChapterAdvancement,
        requestIdea,
    };
}

