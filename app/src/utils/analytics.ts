import { post } from '@/api/client';

declare global {
  interface Window {
    gtag?: (...args: unknown[]) => void;
  }
}

type AnalyticsPayload = Record<string, string | number | boolean | undefined>;

export function trackClientEvent(event: string, payload: AnalyticsPayload = {}) {
  if (typeof window !== 'undefined' && typeof window.gtag === 'function') {
    window.gtag('event', event, payload);
  }

  void post('/events/track', {
    event,
    ...payload,
  }).catch(() => undefined);
}
