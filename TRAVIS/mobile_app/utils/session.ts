import AsyncStorage from '@react-native-async-storage/async-storage';

export type TravisUser = {
  user_id: number;
  full_name: string;
  email: string;
  role: string;
};

export const isTreasurerRole = (role?: string) => {
  const normalized = (role || '').trim().toLowerCase();
  return normalized === 'treasurer' || normalized === 'treasury personnel';
};

export async function getStoredUser(): Promise<TravisUser | null> {
  const value = await AsyncStorage.getItem('travis_user');
  if (!value) return null;
  try {
    return JSON.parse(value) as TravisUser;
  } catch {
    await AsyncStorage.removeItem('travis_user');
    return null;
  }
}

export async function clearStoredUser() {
  await AsyncStorage.removeItem('travis_user');
}
