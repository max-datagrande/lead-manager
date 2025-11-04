/**
 * Constantes para headers HTTP comunes utilizados en integraciones
 */

// Headers de autenticación
export const AUTH_HEADERS = [
  { key: 'Authorization', value: 'Bearer {token}' },
  { key: 'Authorization', value: 'Basic {credentials}' },
  { key: 'X-API-Key', value: '{api_key}' },
  { key: 'X-Auth-Token', value: '{auth_token}' },
  { key: 'X-Access-Token', value: '{access_token}' },
];

// Headers de contenido
export const CONTENT_HEADERS = [
  { key: 'Content-Type', value: 'application/json' },
  { key: 'Content-Type', value: 'application/x-www-form-urlencoded' },
  { key: 'Content-Type', value: 'multipart/form-data' },
  { key: 'Content-Type', value: 'text/plain' },
  { key: 'Accept', value: 'application/json' },
  { key: 'Accept', value: 'application/xml' },
  { key: 'Accept', value: 'text/html' },
  { key: 'Accept-Encoding', value: 'gzip, deflate' },
  { key: 'Accept-Language', value: 'en-US,en;q=0.9' },
];

// Headers de usuario y cliente
export const CLIENT_HEADERS = [
  { key: 'User-Agent', value: 'LeadManager/1.0' },
  { key: 'X-Forwarded-For', value: '{client_ip}' },
  { key: 'X-Real-IP', value: '{client_ip}' },
  { key: 'X-Client-ID', value: '{client_id}' },
  { key: 'X-Request-ID', value: '{request_id}' },
];

// Headers de cache y control
export const CACHE_HEADERS = [
  { key: 'Cache-Control', value: 'no-cache' },
  { key: 'Cache-Control', value: 'max-age=3600' },
  { key: 'Pragma', value: 'no-cache' },
  { key: 'If-None-Match', value: '{etag}' },
  { key: 'If-Modified-Since', value: '{date}' },
];

// Headers de CORS
export const CORS_HEADERS = [
  { key: 'Access-Control-Allow-Origin', value: '*' },
  { key: 'Access-Control-Allow-Methods', value: 'GET, POST, PUT, DELETE' },
  { key: 'Access-Control-Allow-Headers', value: 'Content-Type, Authorization' },
  { key: 'Access-Control-Max-Age', value: '86400' },
];

// Lista completa de todos los headers para usar en datalist
export const ALL_HEADERS = [
  ...AUTH_HEADERS,
  ...CONTENT_HEADERS,
  ...CLIENT_HEADERS,
  ...CACHE_HEADERS,
  ...CORS_HEADERS,
];

// Solo las keys para el datalist del campo key filtradas duplicadas
export const HEADER_KEYS = [...new Set(ALL_HEADERS.map(header => header.key).sort())];

// Solo los values para el datalist del campo value
export const HEADER_VALUES = [...new Set(ALL_HEADERS.map(header => header.value).sort())];

// Headers agrupados por categoría para mejor organización
export const HEADERS_BY_CATEGORY = {
  authentication: AUTH_HEADERS,
  content: CONTENT_HEADERS,
  client: CLIENT_HEADERS,
  cache: CACHE_HEADERS,
  cors: CORS_HEADERS,
} as const;

// Tipo para las categorías de headers
export type HeaderCategory = keyof typeof HEADERS_BY_CATEGORY;

// Tipo para un header individual
export interface HttpHeader {
  key: string;
  value: string;
}
