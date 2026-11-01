import axios from "axios";
import Constants from "expo-constants";
import { notifySessionExpired } from "../utils/sessionEvents";
import {
  clearStoredUser,
  getStoredRefreshToken,
  getStoredSessionId,
  saveMobileSession,
  TravisUser,
} from "../utils/session";

const FALLBACK_APP_HOST = "192.168.1.40";
const expoHostUri = Constants.expoConfig?.hostUri || Constants.expoGoConfig?.debuggerHost;
const appHost = expoHostUri?.split(":")[0] || FALLBACK_APP_HOST;

const ensureTrailingSlash = (url: string): string => `${url.replace(/\/+$/, '')}/`;

// Prefer the deployment URL supplied by Expo. The Metro host is only a local
// development fallback, so production builds never depend on the laptop's IP.
const configuredApiUrl = process.env.EXPO_PUBLIC_API_URL?.trim();
const BASE_URL = ensureTrailingSlash(configuredApiUrl || `http://${appHost}/TRAVIS/api/`);

console.log("TRAVIS API URL:", BASE_URL);

export const APP_ROOT_URL = BASE_URL.replace(/api\/$/i, "");
// The prediction proxies live under Web_app/api and forward requests to the
// local Flask machine-learning service. Derive the URL from the same app root
// so both APIs keep working when EXPO_PUBLIC_API_URL is changed.
const ML_BASE_URL = `${APP_ROOT_URL}Web_app/api/`;

const api = axios.create({
  baseURL: BASE_URL,
  timeout: 10000,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
  withCredentials: true, // Para sa session
});

export const mlApi = axios.create({
  baseURL: ML_BASE_URL,
  timeout: 25000,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

// ============================================================
// REQUEST INTERCEPTOR
// ============================================================
api.interceptors.request.use(async (config) => {

  const [sessionId, refreshToken] = await Promise.all([
    getStoredSessionId(),
    getStoredRefreshToken(),
  ]);
  if (sessionId) config.headers.Cookie = `PHPSESSID=${sessionId}`;
  if (refreshToken) config.headers['X-TRAVIS-Mobile-Token'] = refreshToken;

  const method = config.method?.toUpperCase() || 'UNKNOWN';
  const url = config.url || 'unknown-url';
  const baseURL = config.baseURL || '';
  
  console.log("==================================");
  console.log("REQUEST:", method, url);
  console.log("FULL URL:", baseURL + url);
  console.log("DATA:", config.data);
  console.log("==================================");
  return config;
});

let refreshPromise: Promise<TravisUser> | null = null;

export async function refreshMobileSession(): Promise<TravisUser> {
  if (refreshPromise) return refreshPromise;
  refreshPromise = (async () => {
    const refreshToken = await getStoredRefreshToken();
    if (!refreshToken) throw new Error('No saved mobile session');
    const response = await axios.post(`${BASE_URL}mobile_refresh.php`, {}, {
      timeout: 10000,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-TRAVIS-Mobile-Token': refreshToken,
      },
    });
    const payload = response.data;
    if (!payload?.success || !payload?.session_id || !payload?.refresh_token || !payload?.user) {
      throw new Error('Invalid mobile session response');
    }
    await saveMobileSession(payload.user, payload.session_id, payload.refresh_token);
    return payload.user as TravisUser;
  })().finally(() => { refreshPromise = null; });
  return refreshPromise;
}

// =================================================
// RESPONSE INTERCEPTOR
// =================================================
api.interceptors.response.use(
  (response) => {
    console.log("RESPONSE:", response.data);
    return response;
  },
  async (error) => {
    const errorMessage = error.response?.data?.error || error.message || 'Unknown error';
    const status = error.response?.status || 'No status';
    const url = error.config?.url || 'Unknown URL';
    
    console.log("==================================");
    console.log("ERROR:", errorMessage);
    console.log("STATUS:", status);
    console.log("URL:", url);
    console.log("==================================");
    
    const requestPath = String(error.config?.url || '').toLowerCase();
    const isAuthenticationRequest = requestPath.includes('login.php');

    const isRefreshRequest = requestPath.includes('mobile_refresh.php');
    const originalRequest = error.config as typeof error.config & { _travisRetried?: boolean };
    if (error.response?.status === 401 && !isAuthenticationRequest && !isRefreshRequest && !originalRequest?._travisRetried) {
      try {
        originalRequest._travisRetried = true;
        await refreshMobileSession();
        const sessionId = await getStoredSessionId();
        if (sessionId) originalRequest.headers.Cookie = `PHPSESSID=${sessionId}`;
        return api.request(originalRequest);
      } catch {
        await clearStoredUser();
        notifySessionExpired();
      }
    }
    
    return Promise.reject(error);
  }
);

export default api;
