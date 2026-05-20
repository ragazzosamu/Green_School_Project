import axios from 'axios';

const apiClient = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  withCredentials: false,
});

// Inietta il token Bearer in ogni richiesta
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('sanctum_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error),
);

// Su 401 (token scaduto) → puliamo tutto e mandiamo al login
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('sanctum_token');
      localStorage.removeItem('user');
      window.location.href = '/react/login';
    }
    return Promise.reject(error);
  },
);

export default apiClient;