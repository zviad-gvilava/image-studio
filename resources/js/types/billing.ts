/** An exact dollar amount from the server, plus a rounded version for display. */
export type Money = {
    exact: string;
    formatted: string;
};

export type CreditSummary = {
    balance: Money;
    reserved: Money;
    available: Money;
};

export type CreditPack = {
    amount: number;
    label: string;
};

export type CreditTransaction = {
    id: number;
    type: 'purchase' | 'generation_charge';
    label: string;
    amount: Money;
    description: string | null;
    created_at: string;
};
