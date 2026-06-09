// jwt_project/src/utils/jwtHelper.js

/**
 * Base64URL encoding helper
 */
export function base64UrlEncode(str) {
  try {
    const base64 = btoa(unescape(encodeURIComponent(str)));
    return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  } catch (e) {
    console.error('Base64 encoding error:', e);
    return '';
  }
}

/**
 * Base64URL decoding helper
 */
export function base64UrlDecode(str) {
  let base64 = str.replace(/-/g, '+').replace(/_/g, '/');
  while (base64.length % 4) {
    base64 += '=';
  }
  try {
    return decodeURIComponent(escape(atob(base64)));
  } catch {
    // Return fallback decoded atob if not valid UTF-8 URI
    try {
      return atob(base64);
    } catch (e) {
      console.error('Base64 decoding error:', e);
      return '';
    }
  }
}

/**
 * Convert ArrayBuffer to Base64URL
 */
export function bufferToBase64Url(buffer) {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  for (let i = 0; i < bytes.byteLength; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

/**
 * Parse a JWT token into its JSON components without signature verification
 */
export function parseJwt(token) {
  const parts = token.split('.');
  if (parts.length !== 3) {
    throw new Error('JWT must have exactly 3 parts separated by dots.');
  }
  
  const [headerBase64, payloadBase64, signatureBase64] = parts;
  
  let header, payload;
  const decodedHeader = base64UrlDecode(headerBase64);
  const decodedPayload = base64UrlDecode(payloadBase64);

  if (!decodedHeader) {
    throw new Error('Failed to base64url decode header segment.');
  }
  if (!decodedPayload) {
    throw new Error('Failed to base64url decode payload segment.');
  }
  
  try {
    header = JSON.parse(decodedHeader);
  } catch {
    throw new Error('Failed to parse Header: segment is not valid JSON.');
  }
  
  try {
    payload = JSON.parse(decodedPayload);
  } catch {
    throw new Error('Failed to parse Payload: segment is not valid JSON.');
  }
  
  return {
    header,
    payload,
    signature: signatureBase64,
    parts
  };
}

/**
 * Signs a header and payload to produce a full JWT
 */
export async function signToken(headerObj, payloadObj, secret) {
  const headerStr = JSON.stringify(headerObj);
  const payloadStr = JSON.stringify(payloadObj);
  
  const base64Header = base64UrlEncode(headerStr);
  const base64Payload = base64UrlEncode(payloadStr);
  
  const tokenInput = `${base64Header}.${base64Payload}`;
  
  const encoder = new TextEncoder();
  const keyData = encoder.encode(secret);
  const messageData = encoder.encode(tokenInput);
  
  const cryptoKey = await window.crypto.subtle.importKey(
    'raw',
    keyData,
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign']
  );
  
  const signatureBuffer = await window.crypto.subtle.sign(
    'HMAC',
    cryptoKey,
    messageData
  );
  
  const base64Signature = bufferToBase64Url(signatureBuffer);
  return `${tokenInput}.${base64Signature}`;
}

/**
 * Verifies the cryptographic signature and expiration of a JWT locally in the browser
 */
export async function verifyTokenLocally(token, secret) {
  const { header, payload, signature, parts } = parseJwt(token);
  
  if (header.alg !== 'HS256') {
    throw new Error('Only HS256 algorithm is supported locally.');
  }
  
  const input = `${parts[0]}.${parts[1]}`;
  const encoder = new TextEncoder();
  const keyData = encoder.encode(secret);
  const messageData = encoder.encode(input);
  
  const cryptoKey = await window.crypto.subtle.importKey(
    'raw',
    keyData,
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign']
  );
  
  const signatureBuffer = await window.crypto.subtle.sign(
    'HMAC',
    cryptoKey,
    messageData
  );
  
  const expectedSignature = bufferToBase64Url(signatureBuffer);
  
  if (expectedSignature !== signature) {
    throw new Error('Cryptographic signature verification failed.');
  }
  
  // Expiration check
  if (payload.exp && payload.exp < Math.floor(Date.now() / 1000)) {
    throw new Error('Token has expired.');
  }
  
  // Not Before check
  if (payload.nbf && payload.nbf > Math.floor(Date.now() / 1000)) {
    throw new Error('Token is not active yet.');
  }
  
  return payload;
}
