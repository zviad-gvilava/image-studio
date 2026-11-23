import type { Money } from './billing';

export type ProfitLine = {
    generations: number;
    charged: Money;
    provider_cost: Money;
    profit: Money;
    margin_percent: string | null;
};

export type PixelforgeComparison = {
    pixelforge: { generations: number; cost: Money } | null;
    matches: boolean | null;
};

export type ReportUser = {
    id: number | null;
    name: string;
    email: string | null;
};

export type UserProfitLine = ProfitLine & { user: ReportUser };

export type ReportDay = ProfitLine &
    PixelforgeComparison & {
        date: string;
        users: UserProfitLine[];
    };

export type ProfitReport = {
    range: { from: string; to: string };
    totals: ProfitLine & PixelforgeComparison;
    days: ReportDay[];
    users: UserProfitLine[];
    pixelforge_available: boolean;
    max_days: number;
};
