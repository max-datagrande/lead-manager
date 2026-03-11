/**
 * Extracts query parameter keys and values from a URL string.
 * Returns an empty array if the URL is invalid.
 *
 * @param {string} url
 * @returns {{ key: string, value: string }[]}
 */
export function extractUrlParams(url) {
  try {
    return [...new URL(url).searchParams.entries()].map(([key, value]) => ({ key, value }))
  } catch {
    return []
  }
}
