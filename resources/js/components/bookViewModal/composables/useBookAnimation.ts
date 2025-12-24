import { ref, onBeforeUnmount } from 'vue';
import type { CardPosition, AnimationPhase } from '../types';

const TRANSITION_EASING = 'cubic-bezier(0.4, 0, 0.2, 1)';

export function useBookAnimation() {
    const animationPhase = ref<AnimationPhase>('initial');
    const cardStyle = ref<Record<string, string>>({});
    const isRendered = ref(false);
    const isClosing = ref(false);
    const frontVisible = ref(true);
    const backVisible = ref(false);
    const showContent = ref(false);
    const expandedRect = ref<CardPosition | null>(null);
    const coverRect = ref<{ width: number; left: number } | null>(null);
    const isCoverFading = ref(false);
    const isBookOpened = ref(false);
    const isPageTurning = ref(false);

    const scheduledTimeouts: number[] = [];

    const scheduleTimeout = (callback: () => void, delay: number): void => {
        const id = window.setTimeout(() => {
            callback();
            const index = scheduledTimeouts.indexOf(id);
            if (index !== -1) {
                scheduledTimeouts.splice(index, 1);
            }
        }, delay);
        scheduledTimeouts.push(id);
    };

    const clearScheduledTimeouts = (): void => {
        scheduledTimeouts.forEach(timeoutId => window.clearTimeout(timeoutId));
        scheduledTimeouts.length = 0;
    };

    const setCardStyle = (style: Partial<CSSStyleDeclaration>): void => {
        const nextStyle: Record<string, string> = {};
        Object.entries(style).forEach(([key, value]) => {
            if (typeof value === 'number') {
                nextStyle[key] = `${value}px`;
            } else if (typeof value === 'string') {
                nextStyle[key] = value;
            }
        });
        cardStyle.value = nextStyle;
    };

    const calculateCoverDimensions = (): CardPosition => {
        const maxHeight = window.innerHeight * 0.96;
        const maxWidth = window.innerWidth * 0.96;
        
        // Calculate dimensions maintaining 3:4 aspect ratio (0.67 width:height for book cover)
        // Start with height-based calculation
        let coverHeight = maxHeight;
        let coverWidth = coverHeight * 0.67;
        
        // If the calculated width exceeds the max width, constrain by width instead
        if (coverWidth > maxWidth) {
            coverWidth = maxWidth;
            coverHeight = coverWidth / 0.67;
        }
        
        const coverTop = (window.innerHeight - coverHeight) / 2;
        const coverLeft = (window.innerWidth - coverWidth) / 2;

        return {
            top: coverTop,
            left: coverLeft,
            width: coverWidth,
            height: coverHeight,
        };
    };

    const startAnimation = async (
        cardPosition: CardPosition | null,
        onLoadBook: () => void
    ): Promise<void> => {
        clearScheduledTimeouts();
        showContent.value = false;
        animationPhase.value = 'initial';
        frontVisible.value = true;
        backVisible.value = true;
        isClosing.value = false;
        isBookOpened.value = false;
        isPageTurning.value = false;
        isCoverFading.value = false;
        coverRect.value = null;

        const coverDimensions = calculateCoverDimensions();
        expandedRect.value = coverDimensions;

        if (!cardPosition) {
            onLoadBook();
            animationPhase.value = 'complete';
            showContent.value = true;
            setCardStyle({
                position: 'fixed',
                top: `${coverDimensions.top}px`,
                left: `${coverDimensions.left}px`,
                width: `${coverDimensions.width}px`,
                height: `${coverDimensions.height}px`,
                transform: 'none',
                zIndex: '9999',
            });
            return;
        }

        setCardStyle({
            position: 'fixed',
            top: `${cardPosition.top}px`,
            left: `${cardPosition.left}px`,
            width: `${cardPosition.width}px`,
            height: `${cardPosition.height}px`,
            transform: 'none',
            zIndex: '9999',
            transition: 'none',
        });

        onLoadBook();

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                animationPhase.value = 'flipping';

                setCardStyle({
                    position: 'fixed',
                    top: `${coverDimensions.top}px`,
                    left: `${coverDimensions.left}px`,
                    width: `${coverDimensions.width}px`,
                    height: `${coverDimensions.height}px`,
                    transform: 'none',
                    zIndex: '9999',
                    transition: `all 600ms ${TRANSITION_EASING}`,
                });

                scheduleTimeout(() => {
                    showContent.value = true;
                    animationPhase.value = 'complete';
                }, 600);
            });
        });
    };

    const openBook = (): void => {
        if (isPageTurning.value || isBookOpened.value || !expandedRect.value) {
            return;
        }

        isPageTurning.value = true;

        const currentWidth = expandedRect.value.width;
        const currentLeft = expandedRect.value.left;
        coverRect.value = { width: currentWidth, left: currentLeft };

        const fullWidth = Math.min(window.innerWidth * 0.96, expandedRect.value.height * 1.4);
        const fullLeft = (window.innerWidth - fullWidth) / 2;

        expandedRect.value = {
            ...expandedRect.value,
            width: fullWidth,
            left: fullLeft,
        };

        setCardStyle({
            position: 'fixed',
            top: `${expandedRect.value.top}px`,
            left: `${fullLeft}px`,
            width: `${fullWidth}px`,
            height: `${expandedRect.value.height}px`,
            transform: 'none',
            zIndex: '9999',
            transition: 'all 600ms cubic-bezier(0.4, 0, 0.2, 1)',
        });

        scheduleTimeout(() => {
            isCoverFading.value = true;
        }, 50);

        scheduleTimeout(() => {
            isBookOpened.value = true;
            isPageTurning.value = false;
            isCoverFading.value = false;
        }, 1100);
    };

    const reverseAnimation = async (
        cardPosition: CardPosition | null,
        onComplete: () => void
    ): Promise<void> => {
        if (isClosing.value) {
            return;
        }

        if (!cardPosition || !expandedRect.value) {
            onComplete();
            return;
        }

        clearScheduledTimeouts();
        isClosing.value = true;
        animationPhase.value = 'flipping';
        showContent.value = false;
        isBookOpened.value = false;

        const coverDimensions = calculateCoverDimensions();

        setCardStyle({
            position: 'fixed',
            top: `${coverDimensions.top}px`,
            left: `${coverDimensions.left}px`,
            width: `${coverDimensions.width}px`,
            height: `${coverDimensions.height}px`,
            transform: 'none',
            zIndex: '9999',
            transition: 'all 300ms ease-out',
        });

        scheduleTimeout(() => {
            requestAnimationFrame(() => {
                setCardStyle({
                    position: 'fixed',
                    top: `${cardPosition.top}px`,
                    left: `${cardPosition.left}px`,
                    width: `${cardPosition.width}px`,
                    height: `${cardPosition.height}px`,
                    transform: 'none',
                    zIndex: '9999',
                    transition: 'all 500ms ease-in-out',
                });

                scheduleTimeout(() => {
                    onComplete();
                }, 500);
            });
        }, 300);
    };

    const resetState = (): void => {
        clearScheduledTimeouts();
        isClosing.value = false;
        isRendered.value = false;
        animationPhase.value = 'initial';
        showContent.value = false;
        frontVisible.value = true;
        backVisible.value = true;
        expandedRect.value = null;
        coverRect.value = null;
        cardStyle.value = {};
        isBookOpened.value = false;
        isPageTurning.value = false;
        isCoverFading.value = false;
    };

    onBeforeUnmount(() => {
        clearScheduledTimeouts();
    });

    return {
        animationPhase,
        cardStyle,
        isRendered,
        isClosing,
        frontVisible,
        backVisible,
        showContent,
        expandedRect,
        coverRect,
        isCoverFading,
        isBookOpened,
        isPageTurning,
        scheduleTimeout,
        clearScheduledTimeouts,
        setCardStyle,
        startAnimation,
        openBook,
        reverseAnimation,
        resetState,
    };
}

