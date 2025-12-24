import { ref, computed, onMounted, onUnmounted } from 'vue';

/**
 * Composable for responsive book viewing.
 * Handles screen size detection and single-page mode for smaller screens.
 */
export function useResponsiveBook() {
    // Track if we're in single-page mode (smaller screens)
    const isSinglePageMode = ref(false);
    
    // In single-page mode, track which page of the spread to show ('left' or 'right')
    const singlePageSide = ref<'left' | 'right'>('right');
    
    // Breakpoint for single-page mode (below lg = 1024px)
    const SINGLE_PAGE_BREAKPOINT = 1024;
    
    const updateScreenSize = () => {
        isSinglePageMode.value = window.innerWidth < SINGLE_PAGE_BREAKPOINT;
    };
    
    onMounted(() => {
        updateScreenSize();
        window.addEventListener('resize', updateScreenSize);
    });
    
    onUnmounted(() => {
        window.removeEventListener('resize', updateScreenSize);
    });
    
    /**
     * Navigate to the next page in single-page mode.
     * Returns true if we should advance to the next spread, false if we just switched sides.
     */
    const goToNextSinglePage = (): boolean => {
        if (singlePageSide.value === 'left') {
            singlePageSide.value = 'right';
            return false; // Stayed on same spread
        } else {
            singlePageSide.value = 'left';
            return true; // Need to advance to next spread
        }
    };
    
    /**
     * Navigate to the previous page in single-page mode.
     * Returns true if we should go to the previous spread, false if we just switched sides.
     */
    const goToPrevSinglePage = (): boolean => {
        if (singlePageSide.value === 'right') {
            singlePageSide.value = 'left';
            return false; // Stayed on same spread
        } else {
            singlePageSide.value = 'right';
            return true; // Need to go to previous spread
        }
    };
    
    /**
     * Reset to the default page side (right for title/start of chapter).
     */
    const resetSinglePageSide = () => {
        singlePageSide.value = 'right';
    };
    
    /**
     * Set to the left side (e.g., when going back to end of previous spread).
     */
    const setSinglePageToLeft = () => {
        singlePageSide.value = 'left';
    };
    
    /**
     * Set to the right side.
     */
    const setSinglePageToRight = () => {
        singlePageSide.value = 'right';
    };
    
    // Computed helper for showing left page
    const showLeftPage = computed(() => {
        return !isSinglePageMode.value || singlePageSide.value === 'left';
    });
    
    // Computed helper for showing right page
    const showRightPage = computed(() => {
        return !isSinglePageMode.value || singlePageSide.value === 'right';
    });
    
    return {
        isSinglePageMode,
        singlePageSide,
        showLeftPage,
        showRightPage,
        goToNextSinglePage,
        goToPrevSinglePage,
        resetSinglePageSide,
        setSinglePageToLeft,
        setSinglePageToRight,
    };
}

