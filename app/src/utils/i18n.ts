/**
 * WordPress i18n wrapper.
 * In production, wp.i18n is available on the global scope.
 * Falls back to identity function for dev/standalone.
 */

interface WpI18n {
  __: (text: string, domain?: string) => string;
  _n: (single: string, plural: string, number: number, domain?: string) => string;
  sprintf: (format: string, ...args: unknown[]) => string;
}

declare global {
  interface Window {
    wp?: {
      i18n?: WpI18n;
    };
  }
}

const DOMAIN = 'guardianes-formularios';

export function __(text: string): string {
  return window.wp?.i18n?.__(text, DOMAIN) ?? text;
}

export function _n(single: string, plural: string, number: number): string {
  return window.wp?.i18n?._n(single, plural, number, DOMAIN) ?? (number === 1 ? single : plural);
}
