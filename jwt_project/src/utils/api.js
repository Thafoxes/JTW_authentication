export const backendBaseUrl = (hostname => {
  const localHosts = ['localhost', '127.0.0.1'];
  return localHosts.includes(hostname)
    ? 'http://localhost/JTW_authentication/backend'
    : '/backend';
})(window.location.hostname);

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
    throw new Error('Invalid JSON response from server.');
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
