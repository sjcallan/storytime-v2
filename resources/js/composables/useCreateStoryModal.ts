import { ref } from 'vue';

// Shared state for the create story modal
const isOpen = ref(false);

export function useCreateStoryModal() {
    const open = () => {
        isOpen.value = true;
    };

    const close = () => {
        isOpen.value = false;
    };

    return {
        isOpen,
        open,
        close,
    };
}

