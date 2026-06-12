export const backendBaseUrl = (hostname => {
  const localHosts = ['localhost', '127.0.0.1'];
  return localHosts.includes(hostname)
    ? 'http://localhost/JTW_authentication/backend'
    : '/backend';
})(window.location.hostname);

export function isTokenExpired(token) {
  if (!token) return true;
  try {
    const parts = token.split('.');
    if (parts.length !== 3) return true;
    const base64Url = parts[1];
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
    const jsonPayload = decodeURIComponent(
      atob(base64)
        .split('')
        .map(c => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2))
        .join('')
    );
    const payload = JSON.parse(jsonPayload);
    if (!payload || !payload.exp) {
      return true;
    }
    const currentTime = Math.floor(Date.now() / 1000);
    return payload.exp < currentTime;
  } catch {
    return true;
  }
}

export async function authFetch(endpoint, options = {}) {
  const token = localStorage.getItem('jwt_token');
  const headers = {
    'Content-Type': 'application/json',
    ...options.headers,
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(`${backendBaseUrl}${endpoint}`, {
    ...options,
    headers,
  });

  let payload;
  try {
    payload = await response.json();
  } catch (err) {
    console.error('Failed to parse JSON response from', endpoint, err);
    throw new Error('Invalid JSON response from server.', { cause: err });
  }


  if (!response.ok) {
    const message = payload?.message || `Request failed with status ${response.status}`;
    const error = new Error(message);
    error.response = response;
    error.payload = payload;
    throw error;
  }

  return payload;
}
