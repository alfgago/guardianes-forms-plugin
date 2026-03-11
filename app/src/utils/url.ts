/**
 * Read the `?p=` query parameter from the current URL.
 */
export function getPage(): string {
  const params = new URLSearchParams(window.location.search);
  return params.get('p') ?? '';
}

/**
 * Read a specific query parameter from the current URL.
 */
export function getParam(key: string): string | null {
  const params = new URLSearchParams(window.location.search);
  return params.get(key);
}

/**
 * Navigate to a new `?p=` page using pushState (no full reload).
 */
export function navigateTo(page: string, extra?: Record<string, string>): void {
  const params = new URLSearchParams(window.location.search);
  if (page) {
    params.set('p', page);
  } else {
    params.delete('p');
  }
  if (extra) {
    for (const [key, value] of Object.entries(extra)) {
      if (value) {
        params.set(key, value);
      } else {
        params.delete(key);
      }
    }
  }
  const qs = params.toString();
  const newUrl = `${window.location.pathname}${qs ? '?' + qs : ''}`;
  window.history.pushState({}, '', newUrl);
  window.dispatchEvent(new PopStateEvent('popstate'));
}

/**
 * Build a page URL for <a> links (for right-click, open in new tab).
 */
export function buildPageUrl(page: string, extra?: Record<string, string>): string {
  const params = new URLSearchParams(window.location.search);
  if (page) {
    params.set('p', page);
  } else {
    params.delete('p');
  }
  if (extra) {
    for (const [key, value] of Object.entries(extra)) {
      if (value) params.set(key, value);
    }
  }
  const qs = params.toString();
  return `${window.location.pathname}${qs ? '?' + qs : ''}`;
}
