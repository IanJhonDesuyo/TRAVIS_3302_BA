import axios from "axios";
import { Alert } from "react-native";

// ========================================
// PALITAN ANG IP ADDRESS NG COMPUTER
// ========================================
const SERVER_IP = "10.71.31.24"; // <-- IP ng PC
const PORT = "80"; // Default Apache port

// Isama ang TRAVIS_3302_BA sa path
const BASE_URL = `http://${SERVER_IP}:${PORT}/TRAVIS_3302_BA/TRAVIS/api/`;

const api = axios.create({
  baseURL: BASE_URL,
  timeout: 10000,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
  withCredentials: true, // Para sa session
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
  (error) => {
    const errorMessage = error.response?.data?.error || error.message || 'Unknown error';
    const status = error.response?.status || 'No status';
    const url = error.config?.url || 'Unknown URL';
    
    console.log("==================================");
    console.log("ERROR:", errorMessage);
    console.log("STATUS:", status);
    console.log("URL:", url);
    console.log("==================================");
    
    if (error.response?.status === 401) {
      Alert.alert("Session Expired", "Please login again.");
    }
    
    return Promise.reject(error);
  }
);

export default api;