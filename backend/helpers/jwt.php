<?php
// backend/helpers/jwt.php

class JWT {
    /**
     * Encode an array payload into a JWT string.
     */
    public static function encode(array $payload, string $secret, string $alg = 'HS256'): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => $alg]);
        $payloadJson = json_encode($payload);
        
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payloadJson);
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Decode a JWT string into an array payload, verifying signature and expiration.
     */
    public static function decode(string $jwt, string $secret): array {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new Exception("Invalid token format.");
        }
        
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
        
        $header = json_decode(self::base64UrlDecode($base64UrlHeader), true);
        $payload = json_decode(self::base64UrlDecode($base64UrlPayload), true);
        $signature = self::base64UrlDecode($base64UrlSignature);
        
        if (!$header || !$payload) {
            throw new Exception("Invalid JSON encoding in token.");
        }
        
        if (!isset($header['alg']) || $header['alg'] !== 'HS256') {
            throw new Exception("Unsupported algorithm. Only HS256 is supported.");
        }
        
        // Verify signature
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        if (!hash_equals($expectedSignature, $signature)) {
            throw new Exception("Signature verification failed.");
        }
        
        // Verify expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception("Token has expired.");
        }
        
        // Verify not before
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            throw new Exception("Token is not active yet.");
        }
        
        return $payload;
    }

    /**
     * Base64URL encoding helper.
     */
    private static function base64UrlEncode(string $data): string {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Base64URL decoding helper.
     */
    private static function base64UrlDecode(string $data): string {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
