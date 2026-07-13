import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  withCredentials: true,
  withXSRFToken: true,
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) window.dispatchEvent(new CustomEvent('auth:expired'));
    return Promise.reject(error);
  },
);

export const csrf = () => axios.get('/sanctum/csrf-cookie', { withCredentials: true });
export default api;
