// Types
export * from './types';

// Composables
export { useBookAnimation } from './composables/useBookAnimation';
export { useChapterPagination } from './composables/useChapterPagination';
export { useResponsiveBook } from './composables/useResponsiveBook';
export { useSwipeGesture } from './composables/useSwipeGesture';
export type { ChapterCreatedPayload, ChapterUpdatedPayload, ReadingHistoryCallback } from './composables/useChapterPagination';

// Components
export { default as BookCover } from './BookCover.vue';
export { default as BookSpine } from './BookSpine.vue';
export { default as BookPageTexture } from './BookPageTexture.vue';
export { default as BookPageDecorative } from './BookPageDecorative.vue';
export { default as BookLeftPage } from './BookLeftPage.vue';
export { default as BookRightPage } from './BookRightPage.vue';
export { default as BookTitlePage } from './BookTitlePage.vue';
export { default as BookChapterContent } from './BookChapterContent.vue';
export { default as BookEditForm } from './BookEditForm.vue';
export { default as BookFooterNav } from './BookFooterNav.vue';
export { default as BookHeaderControls } from './BookHeaderControls.vue';
export { default as BookLoadingOverlay } from './BookLoadingOverlay.vue';
export { default as CreateChapterForm } from './CreateChapterForm.vue';
export { default as NextChapterPreview } from './NextChapterPreview.vue';
export { default as DeleteConfirmDialog } from './DeleteConfirmDialog.vue';
export { default as CharacterGrid } from './CharacterGrid.vue';
export { default as CharacterDetail } from './CharacterDetail.vue';
export { default as CharacterChatModal } from './CharacterChatModal.vue';
export { default as ChapterEditModal } from './ChapterEditModal.vue';
