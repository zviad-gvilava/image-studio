import type { Money } from '@/types';

/**
 * Whole nanodollars, for comparing amounts without floating point. Not for arithmetic:
 * every amount the user is charged is calculated on the server.
 */
function toNanos(money: Money): bigint {
    return BigInt(money.exact.replace('.', ''));
}

export function isLessThan(amount: Money, other: Money): boolean {
    return toNanos(amount) < toNanos(other);
}

export function isZero(amount: Money): boolean {
    return toNanos(amount) === 0n;
}

/** Every decimal the server sent, e.g. "$0.381887500" or "-$0.071200000". */
export function formatExactMoney(money: Money): string {
    return money.exact.startsWith('-')
        ? `-$${money.exact.slice(1)}`
        : `$${money.exact}`;
}
