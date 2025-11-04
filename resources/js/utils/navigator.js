export function getCookie(name) {
  const cookies = document.cookie.split('; ').reduce((acc, kv) => {
    const [key, val] = kv.split('=');
    acc[key] = decodeURIComponent(val);
    return acc;
  }, {});
  return cookies[name] ?? null;
}
