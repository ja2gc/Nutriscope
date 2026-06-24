const CACHE_KEY = "usd_php_rate";
const CACHE_TTL_MS = 6 * 60 * 60 * 1000; // 6 hours
const FALLBACK_RATE = 56; // PHP per USD

interface CacheEntry {
  rate: number;
  fetchedAt: number;
}

export async function fetchUsdToPhpRate(): Promise<number> {
  try {
    const cached = localStorage.getItem(CACHE_KEY);
    if (cached) {
      const entry: CacheEntry = JSON.parse(cached);
      if (Date.now() - entry.fetchedAt < CACHE_TTL_MS) {
        return entry.rate;
      }
    }
  } catch {
    // corrupt cache — ignore
  }

  try {
    const res = await fetch("https://open.er-api.com/v6/latest/USD");
    if (!res.ok) throw new Error("rate fetch failed");
    const data = await res.json();
    const rate: number = data?.rates?.PHP;
    if (!rate || typeof rate !== "number") throw new Error("bad rate");

    try {
      localStorage.setItem(CACHE_KEY, JSON.stringify({ rate, fetchedAt: Date.now() }));
    } catch {
      // storage quota — ignore
    }

    return rate;
  } catch {
    return FALLBACK_RATE;
  }
}
