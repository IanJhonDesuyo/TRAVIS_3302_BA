import axios from "axios";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { notifySessionExpired } from "../utils/sessionEvents";

// The phone must use the computer's LAN IP; localhost would point to the phone.
// Override this after changing networks with EXPO_PUBLIC_API_URL when needed.
const BASE_URL = (
  process.env.EXPO_PUBLIC_API_URL || "http://192.168.1.10/TRAVIS/api/"
).replace(/\/?$/, "/");

export const APP_ROOT_URL = BASE_URL.replace(/api\/$/, "");
const APP_HOST = BASE_URL.match(/^(https?:\/\/[^/:]+)(?::\d+)?/i)?.[1] || "http://192.168.1.10";
export const CV_STREAM_URL = `${APP_HOST}:5000/`;
export const MOBILE_STREAM_URL = `${APP_ROOT_URL}Web_app/api/video_feed.php?client=mobile`;
export const MOBILE_SNAPSHOT_URL = `${APP_ROOT_URL}Web_app/api/video_snapshot.php?client=mobile`;

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
api.interceptors.request.use((config) => {

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

    if (error.response?.status === 401 && !isAuthenticationRequest) {
      await AsyncStorage.removeItem('travis_user');
      notifySessionExpired();
    }
    
    return Promise.reject(error);
  }
);

export default api;
