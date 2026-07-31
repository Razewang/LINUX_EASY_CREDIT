export const DEFAULT_AMOUNT_LIMITS = Object.freeze({
    minAmount: 0.01,
    maxAmount: 9999.99
});

export function parseAmountLimits(payload) {
    const minAmount = Number(payload?.data?.min_amount);
    const maxAmount = Number(payload?.data?.max_amount);

    if (
        !Number.isFinite(minAmount)
        || !Number.isFinite(maxAmount)
        || minAmount <= 0
        || maxAmount < minAmount
    ) {
        return { ...DEFAULT_AMOUNT_LIMITS };
    }

    return { minAmount, maxAmount };
}

export async function loadAmountLimits(fetchImpl = globalThis.fetch) {
    try {
        const response = await fetchImpl('./api/config', {
            headers: { Accept: 'application/json' }
        });

        if (!response.ok) {
            return { ...DEFAULT_AMOUNT_LIMITS };
        }

        return parseAmountLimits(await response.json());
    } catch (_) {
        return { ...DEFAULT_AMOUNT_LIMITS };
    }
}

export function applyAmountLimits(input, limits) {
    input.min = String(limits.minAmount);
    input.max = String(limits.maxAmount);
}
