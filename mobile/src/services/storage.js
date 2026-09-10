import * as SecureStore from 'expo-secure-store';
import { TOKEN_KEY, REFRESH_KEY } from '../api/axios';

const PUSH_TOKEN_KEY = 'push_token';

export async function getStoredToken() {
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function getRefreshToken() {
  return SecureStore.getItemAsync(REFRESH_KEY);
}

export async function storeTokens(access, refresh) {
  await SecureStore.setItemAsync(TOKEN_KEY, access);
  if (refresh) await SecureStore.setItemAsync(REFRESH_KEY, refresh);
}

export async function clearTokens() {
  await SecureStore.deleteItemAsync(TOKEN_KEY);
  await SecureStore.deleteItemAsync(REFRESH_KEY);
}

export async function getPushToken() {
  return SecureStore.getItemAsync(PUSH_TOKEN_KEY);
}

export async function storePushToken(token) {
  await SecureStore.setItemAsync(PUSH_TOKEN_KEY, token);
}
