import { ref } from 'vue';

// Shared state for the create story modal
const isOpen = ref(false);
const createdBookId = ref<string | null>(null);

export function useCreateStoryModal() {
    const open = () => {
        isOpen.value = true;
    };

    const close = () => {
        isOpen.value = false;
    };

    const setCreatedBook = (bookId: string | null) => {
        createdBookId.value = bookId;
    };

    return {
        isOpen,
        createdBookId,
        open,
        close,
        setCreatedBook,
    };
}
