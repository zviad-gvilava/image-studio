import type { TokenRange } from '@/types';

const numberFormat = new Intl.NumberFormat('en-US');

export function formatNumber(value: number): string {
    return numberFormat.format(value);
}

/** "1 generation", "3 generations". */
export function countOf(count: number, noun: string): string {
    return `${formatNumber(count)} ${noun}${count === 1 ? '' : 's'}`;
}

export function formatTokenRange(range: TokenRange): string {
    return range.min === range.max
        ? formatNumber(range.max)
        : `${formatNumber(range.min)}–${formatNumber(range.max)}`;
}

export function formatDate(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
