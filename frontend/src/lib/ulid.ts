const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'
const TIME_LENGTH = 10
const RANDOM_LENGTH = 16

function encodeTime(timestamp: number): string {
  let value = timestamp
  let encoded = ''
  for (let i = TIME_LENGTH; i > 0; i -= 1) {
    const index = value % 32
    encoded = CROCKFORD.charAt(index) + encoded
    value = Math.floor(value / 32)
  }
  return encoded
}

function encodeRandom(): string {
  const bytes = crypto.getRandomValues(new Uint8Array(RANDOM_LENGTH))
  let encoded = ''
  for (const byte of bytes) {
    encoded += CROCKFORD.charAt(byte & 31)
  }
  return encoded
}

export function newUlid(): string {
  return encodeTime(Date.now()) + encodeRandom()
}
