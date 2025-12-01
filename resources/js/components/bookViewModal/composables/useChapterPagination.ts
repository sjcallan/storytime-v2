import { ref, computed } from 'vue';
import type { Chapter, ChapterResponse, PageSpread, ReadingView, ApiFetchFn } from '../types';
import { apiFetch } from '@/composables/ApiFetch';

const CHARS_PER_LINE = 32;
const LINES_PER_FULL_PAGE = 18;
const FIRST_PAGE_LINES = 7;
const CHARS_PER_FULL_PAGE = CHARS_PER_LINE * LINES_PER_FULL_PAGE;
const CHARS_FIRST_PAGE = CHARS_PER_LINE * FIRST_PAGE_LINES;

export function useChapterPagination() {
    const requestApiFetch = apiFetch as ApiFetchFn;

    const currentChapterNumber = ref(0);
    const currentChapter = ref<Chapter | null>(null);
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

    const chapterPages = computed(() => {
        if (!currentChapter.value?.body) {
            return [];
        }

        const body = currentChapter.value.body;
        const paragraphs = body.split('\n\n').filter(p => p.trim());

        if (paragraphs.length === 0) {
            return [];
        }

        const pages: string[][] = [];
        let currentPage: string[] = [];
        let currentPageChars = 0;
        let pageIndex = 0;

        for (const paragraph of paragraphs) {
            const maxChars = pageIndex === 0 ? CHARS_FIRST_PAGE : CHARS_PER_FULL_PAGE;
            const paragraphChars = paragraph.length + 20;

            if (currentPageChars + paragraphChars > maxChars && currentPage.length > 0) {
                pages.push(currentPage);
                currentPage = [paragraph];
                currentPageChars = paragraphChars;
                pageIndex++;
            } else {
                currentPage.push(paragraph);
                currentPageChars += paragraphChars;
            }
        }

        if (currentPage.length > 0) {
            pages.push(currentPage);
        }

        return pages;
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

    const loadChapter = async (bookId: string, chapterNumber: number): Promise<void> => {
        if (!bookId || isLoadingChapter.value) {
            return;
        }

        isLoadingChapter.value = true;
        chapterError.value = null;

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
        } else {
            const nextNumber = currentChapterNumber.value + 1;

            if (nextNumber <= totalChapters.value) {
                loadChapter(bookId, nextNumber);
            } else {
                currentChapter.value = null;
                currentChapterNumber.value = nextNumber;
                currentSpreadIndex.value = 0;
                readingView.value = 'create-chapter';
            }
        }
    };

    const goToPreviousChapter = (bookId: string): void => {
        if (isLoadingChapter.value || isPageFlipping.value) {
            return;
        }

        if (hasPrevSpread.value) {
            currentSpreadIndex.value--;
        } else if (currentChapterNumber.value > 1) {
            loadChapter(bookId, currentChapterNumber.value - 1);
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

    const resetChapterState = (): void => {
        currentChapterNumber.value = 0;
        currentChapter.value = null;
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
    };

    return {
        currentChapterNumber,
        currentChapter,
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
        loadChapter,
        generateNextChapter,
        goToChapter1,
        goToNextChapter,
        goToPreviousChapter,
        goBackToTitlePage,
        resetChapterState,
    };
}

