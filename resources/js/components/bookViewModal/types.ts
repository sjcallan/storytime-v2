export interface Profile {
    id: string;
    name: string;
}

export interface Character {
    id: string;
    name: string;
    gender: string | null;
    description: string | null;
    type: string | null;
    age: string | null;
    nationality: string | null;
    backstory: string | null;
    portrait_image: string | null;
}

export interface ChapterSummary {
    id: string;
    title: string | null;
    sort: number;
    final_chapter: boolean;
}

export type BookType = 'chapter' | 'story' | 'theatre' | 'screenplay';

export interface Book {
    id: string;
    title: string;
    author: string | null;
    genre: string;
    type?: BookType;
    age_level: number | null;
    plot: string | null;
    cover_image: string | null;
    cover_image_status: string | null;
    status: string;
    created_at: string;
    profile?: Profile | null;
    characters?: Character[];
    chapters?: ChapterSummary[];
}

/**
 * Check if a book type uses scene-based format (theatre/screenplay)
 */
export function isSceneBasedBook(type?: BookType | string): boolean {
    return type === 'theatre' || type === 'screenplay';
}

/**
 * Get the label for chapters/scenes based on book type
 */
export function getChapterLabel(type?: BookType | string, capitalize: boolean = true): string {
    const label = isSceneBasedBook(type) ? 'scene' : 'chapter';
    return capitalize ? label.charAt(0).toUpperCase() + label.slice(1) : label;
}

/**
 * Format script dialogue with bold character names.
 * Matches patterns like "CHARACTER NAME: dialogue" or "CHARACTER NAME: (parenthetical) dialogue"
 * and wraps the character name in <strong> tags.
 */
export function formatScriptDialogue(content: string, isScript: boolean): string {
    if (!isScript || !content) {
        return escapeHtml(content);
    }
    
    // Pattern matches: CHARACTER NAME (in caps, may include spaces, hyphens, apostrophes) followed by colon
    // Examples: "ELIZABETH:", "MARY-JANE:", "DR. SMITH:", "O'BRIEN:"
    const dialoguePattern = /^([A-Z][A-Z\s.'-]{0,30}?):\s*/;
    const match = content.match(dialoguePattern);
    
    if (match) {
        const characterName = match[1];
        const restOfLine = content.slice(match[0].length);
        return `<strong class="font-bold">${escapeHtml(characterName)}:</strong> ${escapeHtml(restOfLine)}`;
    }
    
    // Also handle stage directions in parentheses at the start
    const stageDirectionPattern = /^\(([^)]+)\)\s*/;
    const stageMatch = content.match(stageDirectionPattern);
    
    if (stageMatch) {
        const direction = stageMatch[1];
        const restOfLine = content.slice(stageMatch[0].length);
        return `<em class="italic text-amber-700 dark:text-amber-600">(${escapeHtml(direction)})</em> ${escapeHtml(restOfLine)}`;
    }
    
    return escapeHtml(content);
}

/**
 * Escape HTML special characters to prevent XSS
 */
function escapeHtml(text: string): string {
    if (!text) return '';
    const map: Record<string, string> = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, (char) => map[char] || char);
}

export interface InlineImage {
    paragraph_index: number;
    url: string | null;
    prompt: string;
    status?: 'pending' | 'complete';
}

export interface Chapter {
    id: string;
    title: string | null;
    body: string | null;
    image: string | null;
    inline_images: InlineImage[] | null;
    sort: number;
    summary: string | null;
    final_chapter: boolean;
    user_prompt?: string | null;
}

export interface ChapterResponse {
    chapter: Chapter | null;
    total_chapters: number;
    has_next: boolean;
    has_previous?: boolean;
}

export interface ReadingHistory {
    id: string;
    user_id: string;
    book_id: string;
    profile_id: string;
    chapter_id: string | null;
    last_read_at: string;
    current_chapter_number: number;
    book?: Book | null;
}

export interface CardPosition {
    top: number;
    left: number;
    width: number;
    height: number;
}

export interface PageContentItem {
    type: 'paragraph' | 'image';
    content: string;
    imageUrl?: string | null;
    imageStatus?: 'pending' | 'complete';
    imageIndex?: number;
}

export interface PageSpread {
    leftContent: PageContentItem[] | null;
    rightContent: PageContentItem[] | null;
    isFirstSpread: boolean;
    showImage: boolean;
}

export interface BookEditFormData {
    title: string;
    genre: string;
    age_level: string;
    author: string;
    plot: string;
    type: string;
}

export type ReadingView = 'title' | 'chapter-image' | 'chapter-content' | 'create-chapter' | 'toc';

export type AnimationPhase = 'initial' | 'flipping' | 'complete';

export type ApiFetchFn = (
    request: string,
    method?: string,
    data?: Record<string, unknown> | FormData | null,
    isFormData?: boolean | null,
) => Promise<{ data: unknown; error: unknown }>;

