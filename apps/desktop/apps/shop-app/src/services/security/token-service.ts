import jwt from 'jsonwebtoken';
import { randomUUID } from 'node:crypto';

export function signAccessToken(payload: object, secret: string, expiresInSeconds: number) {
  return jwt.sign(payload, secret, { algorithm: 'HS256', expiresIn: expiresInSeconds });
}

export function verifyAccessToken(token: string, secret: string) {
  try {
    return jwt.verify(token, secret) as any;
  } catch (err) {
    return null;
  }
}

export function createRefreshToken() {
  return randomUUID();
}
