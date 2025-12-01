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

export interface Book {
    id: string;
    title: string;
    author: string | null;
    genre: string;
    age_level: number | null;
    plot: string | null;
    cover_image: string | null;
    status: string;
    created_at: string;
    profile?: Profile | null;
    characters?: Character[];
}

export interface Chapter {
    id: string;
    title: string | null;
    body: string | null;
    image: string | null;
    sort: number;
    summary: string | null;
    final_chapter: boolean;
}

export interface ChapterResponse {
    chapter: Chapter | null;
    total_chapters: number;
    has_next: boolean;
    has_previous?: boolean;
}

export interface CardPosition {
    top: number;
    left: number;
    width: number;
    height: number;
}

export interface PageSpread {
    leftContent: string[] | null;
    rightContent: string[] | null;
    isFirstSpread: boolean;
    showImage: boolean;
}

export interface BookEditFormData {
    title: string;
    genre: string;
    age_level: string;
    author: string;
    plot: string;
}

export type ReadingView = 'title' | 'chapter-image' | 'chapter-content' | 'create-chapter';

export type AnimationPhase = 'initial' | 'flipping' | 'complete';

export type ApiFetchFn = (
    request: string,
    method?: string,
    data?: Record<string, unknown> | FormData | null,
    isFormData?: boolean | null,
) => Promise<{ data: unknown; error: unknown }>;

