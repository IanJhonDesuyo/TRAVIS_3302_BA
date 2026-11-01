import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';

export type TravisUser = {
  user_id: number;
  full_name: string;
  email: string;
  role: string;
};

const USER_KEY = 'travis_user';
const SESSION_ID_KEY = 'travis_session_id';
const REFRESH_TOKEN_KEY = 'travis_refresh_token';

export const isTreasurerRole = (role?: string) => {
  const normalized = (role || '').trim().toLowerCase();
  return normalized === 'treasurer' || normalized === 'treasury personnel';
};

export async function getStoredUser(): Promise<TravisUser | null> {
  const value = await AsyncStorage.getItem(USER_KEY);
  if (!value) return null;
  try {
    return JSON.parse(value) as TravisUser;
  } catch {
    await AsyncStorage.removeItem(USER_KEY);
    return null;
  }
}

export async function saveMobileSession(user: TravisUser, sessionId: string, refreshToken: string) {
  await Promise.all([
    AsyncStorage.setItem(USER_KEY, JSON.stringify(user)),
    SecureStore.setItemAsync(SESSION_ID_KEY, sessionId),
    SecureStore.setItemAsync(REFRESH_TOKEN_KEY, refreshToken),
  ]);
}

export async function getStoredSessionId() {
  return SecureStore.getItemAsync(SESSION_ID_KEY);
}

export async function getStoredRefreshToken() {
  return SecureStore.getItemAsync(REFRESH_TOKEN_KEY);
}

export async function clearStoredUser() {
  await Promise.all([
    AsyncStorage.removeItem(USER_KEY),
    SecureStore.deleteItemAsync(SESSION_ID_KEY),
    SecureStore.deleteItemAsync(REFRESH_TOKEN_KEY),
  ]);
}
