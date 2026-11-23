import type { Money } from './billing';

export type GenerationStatus =
    | 'pending'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

export type TokenRange = {
    min: number;
    max: number;
};

export type Generation = {
    id: string;
    status: GenerationStatus;
    is_final: boolean;
    prompt: string;
    size: string;
    size_label: string;
    quality: string;
    quality_label: string;
    reference_images: string[];
    estimated_min_price: Money;
    estimated_max_price: Money;
    price: Money | null;
    tokens: {
        input_text: number;
        input_image: number;
        output: number;
        reasoning: number;
    } | null;
    image_url: string | null;
    image_expired: boolean;
    image_url_expires_at: string | null;
    message: string | null;
    created_at: string;
    completed_at: string | null;
};

export type GenerationInput = {
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
};

export type PriceEstimate = {
    min_price: Money;
    max_price: Money;
    min_cost: Money;
    max_cost: Money;
    markup_percent: number;
    tokens: {
        input_text: TokenRange;
        input_image: number;
        output: number;
        reasoning: TokenRange;
    };
};

export type Option = {
    value: string;
    label: string;
};

export type StudioOptions = {
    sizes: Option[];
    qualities: Option[];
    max_prompt_length: number;
    max_reference_images: number;
};

export type Paginated<T> = {
    data: T[];
    links: {
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        total: number;
    };
};
