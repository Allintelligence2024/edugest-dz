import api from './axios';
import { cacheGet, cacheSet, cacheClear } from '../services/cache';
import AsyncStorage from '@react-native-async-storage/async-storage';

const OFFLINE_QUEUE_KEY = '@edugest_offline_queue';

export async function isOnline() {
  try {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 3000);
    await fetch(api.defaults.baseURL + '/health', {
      method: 'HEAD',
      signal: controller.signal,
    });
    clearTimeout(timeout);
    return true;
  } catch {
    return false;
  }
}

export async function apiGet(key, fetcher, ttl) {
  const online = await isOnline();
  if (online) {
    try {
      const data = await fetcher();
      await cacheSet(key, data);
      return data;
    } catch (err) {
      const cached = await cacheGet(key, ttl || 300000);
      if (cached !== null) return cached;
      throw err;
    }
  }
  const cached = await cacheGet(key, ttl || 300000);
  if (cached !== null) return cached;
  throw new Error('Aucune connexion et aucune donnée en cache.');
}

export async function apiPost(url, data, options = {}) {
  try {
    const result = await api.post(url, data);
    if (options.cacheKey) await cacheClear(options.cacheKey);
    return result;
  } catch (err) {
    if (options.offlineQueue) {
      const queue = JSON.parse(
        (await AsyncStorage.getItem(OFFLINE_QUEUE_KEY)) || '[]',
      );
      queue.push({ url, data, timestamp: Date.now() });
      await AsyncStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
    }
    throw err;
  }
}

export async function flushOfflineQueue() {
  const raw = await AsyncStorage.getItem(OFFLINE_QUEUE_KEY);
  if (!raw) return [];
  const queue = JSON.parse(raw);
  const failed = [];
  for (const item of queue) {
    try {
      await api.post(item.url, item.data);
    } catch {
      failed.push(item);
    }
  }
  await AsyncStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(failed));
  return failed;
}

export async function clearOfflineQueue() {
  await AsyncStorage.removeItem(OFFLINE_QUEUE_KEY);
}

export { cacheGet, cacheSet, cacheClear };
export default { apiGet, apiPost, isOnline, flushOfflineQueue, clearOfflineQueue };
