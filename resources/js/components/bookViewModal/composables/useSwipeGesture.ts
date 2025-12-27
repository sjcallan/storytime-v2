import { ref, type Ref } from 'vue';

interface SwipeGestureOptions {
    threshold?: number;           // Minimum distance to trigger swipe (in px)
    velocityThreshold?: number;   // Minimum velocity to trigger swipe (px/ms)
    preventScroll?: boolean;      // Prevent vertical scrolling during horizontal swipe
}

interface SwipeGestureReturn {
    onTouchStart: (e: TouchEvent) => void;
    onTouchMove: (e: TouchEvent) => void;
    onTouchEnd: (e: TouchEvent) => void;
    isSwiping: Ref<boolean>;
}

export function useSwipeGesture(
    onSwipeLeft: () => void,
    onSwipeRight: () => void,
    options: SwipeGestureOptions = {}
): SwipeGestureReturn {
    const {
        threshold = 50,
        velocityThreshold = 0.3,
        preventScroll = false,
    } = options;

    const startX = ref(0);
    const startY = ref(0);
    const startTime = ref(0);
    const isSwiping = ref(false);
    const isHorizontalSwipe = ref(false);

    const onTouchStart = (e: TouchEvent) => {
        const touch = e.touches[0];
        startX.value = touch.clientX;
        startY.value = touch.clientY;
        startTime.value = Date.now();
        isSwiping.value = true;
        isHorizontalSwipe.value = false;
    };

    const onTouchMove = (e: TouchEvent) => {
        if (!isSwiping.value) {
            return;
        }

        const touch = e.touches[0];
        const deltaX = touch.clientX - startX.value;
        const deltaY = touch.clientY - startY.value;

        // Determine if this is a horizontal swipe (after some movement)
        if (!isHorizontalSwipe.value && (Math.abs(deltaX) > 10 || Math.abs(deltaY) > 10)) {
            isHorizontalSwipe.value = Math.abs(deltaX) > Math.abs(deltaY);
        }

        // Prevent scroll if horizontal swipe and option is enabled
        if (preventScroll && isHorizontalSwipe.value) {
            e.preventDefault();
        }
    };

    const onTouchEnd = (e: TouchEvent) => {
        if (!isSwiping.value) {
            return;
        }

        const touch = e.changedTouches[0];
        const deltaX = touch.clientX - startX.value;
        const deltaY = touch.clientY - startY.value;
        const deltaTime = Date.now() - startTime.value;
        const velocity = Math.abs(deltaX) / deltaTime;

        isSwiping.value = false;

        // Only trigger if it's a horizontal swipe (more horizontal than vertical)
        if (Math.abs(deltaX) <= Math.abs(deltaY)) {
            return;
        }

        // Check if swipe meets threshold or velocity requirements
        const meetsThreshold = Math.abs(deltaX) >= threshold;
        const meetsVelocity = velocity >= velocityThreshold && Math.abs(deltaX) >= threshold / 2;

        if (meetsThreshold || meetsVelocity) {
            if (deltaX < 0) {
                // Swipe left = go forward (next page)
                onSwipeLeft();
            } else {
                // Swipe right = go back (previous page)
                onSwipeRight();
            }
        }
    };

    return {
        onTouchStart,
        onTouchMove,
        onTouchEnd,
        isSwiping,
    };
}

