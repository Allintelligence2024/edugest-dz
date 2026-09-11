import AsyncStorage from '@react-native-async-storage/async-storage';

const CACHE_PREFIX = '@edugest_cache_';
const DEFAULT_TTL = 5 * 60 * 1000;

export async function cacheGet(key, ttl = DEFAULT_TTL) {
  try {
    const raw = await AsyncStorage.getItem(`${CACHE_PREFIX}${key}`);
    if (!raw) return null;
    const { data, timestamp } = JSON.parse(raw);
    if (Date.now() - timestamp > ttl) {
      await AsyncStorage.removeItem(`${CACHE_PREFIX}${key}`);
      return null;
    }
    return data;
  } catch {
    return null;
  }
}

export async function cacheSet(key, data) {
  try {
    await AsyncStorage.setItem(
      `${CACHE_PREFIX}${key}`,
      JSON.stringify({ data, timestamp: Date.now() }),
    );
  } catch {}
}

export async function cacheClear(pattern) {
  try {
    const keys = await AsyncStorage.getAllKeys();
    const target = pattern
      ? keys.filter((k) => k.startsWith(`${CACHE_PREFIX}${pattern}`))
      : keys.filter((k) => k.startsWith(CACHE_PREFIX));
    await AsyncStorage.multiRemove(target);
  } catch {}
}

export async function withCache(key, fetcher, ttl) {
  const cached = await cacheGet(key, ttl);
  if (cached !== null) return cached;
  const fresh = await fetcher();
  await cacheSet(key, fresh);
  return fresh;
}
