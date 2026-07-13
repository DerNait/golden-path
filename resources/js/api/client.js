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

export const csrf = () => {
  // Remove a legacy host-only token before Laravel sets the domain cookie.
  // Keeping both under the same name makes Axios and PHP select different
  // values and produces a CSRF mismatch even after refreshing the token.
  document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/; Secure; SameSite=Lax';

  return axios.get('/sanctum/csrf-cookie', { withCredentials: true });
};
export default api;
