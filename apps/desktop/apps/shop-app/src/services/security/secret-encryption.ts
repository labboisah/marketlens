import { createCipheriv, createDecipheriv, createHash, randomBytes } from 'node:crypto';

export function encryptSecret(value: string, keyMaterial: string): string {
  const key = createHash('sha256').update(keyMaterial).digest();
  const iv = randomBytes(12);
  const cipher = createCipheriv('aes-256-gcm', key, iv);
  const encrypted = Buffer.concat([cipher.update(value, 'utf8'), cipher.final()]);
  return `v1.${iv.toString('base64url')}.${cipher.getAuthTag().toString('base64url')}.${encrypted.toString('base64url')}`;
}

export function decryptSecret(value: string, keyMaterial: string): string {
  const [version, ivValue, tagValue, ciphertext] = value.split('.');
  if (version !== 'v1' || !ivValue || !tagValue || !ciphertext) throw new Error('Unsupported encrypted secret format');
  const decipher = createDecipheriv('aes-256-gcm', createHash('sha256').update(keyMaterial).digest(), Buffer.from(ivValue, 'base64url'));
  decipher.setAuthTag(Buffer.from(tagValue, 'base64url'));
  return Buffer.concat([decipher.update(Buffer.from(ciphertext, 'base64url')), decipher.final()]).toString('utf8');
}