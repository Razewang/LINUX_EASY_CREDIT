const requireEnv = (name) => {
  const value = process.env[name]?.trim();
  if (!value) {
    throw new Error(`Missing required environment variable: ${name}`);
  }
  return value;
};

const parseAmount = (name, fallback) => {
  const raw = process.env[name];
  if (raw === undefined || raw === '') return fallback;

  const value = Number(raw);
  if (!Number.isFinite(value) || value <= 0) {
    throw new Error(`${name} must be a positive number`);
  }
  return value;
};

export function getConfig() {
  return {
    epay: {
      pid: requireEnv('EPAY_PID'),
      key: requireEnv('EPAY_KEY'),
      gateway: (process.env.EPAY_GATEWAY || 'https://credit.linux.do/epay').replace(/\/+$/, ''),
    },
    reward: {
      minAmount: parseAmount('MIN_AMOUNT', 0.01),
      maxAmount: parseAmount('MAX_AMOUNT', 9999.99),
    },
  };
}
