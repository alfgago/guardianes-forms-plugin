import type { ApiError } from '@/types';

interface InitData {
  restUrl: string;
  nonce: string;
}

let _init: InitData | null = null;

function getInit(): InitData {
  if (_init) return _init;

  // Try each panel's init data
  const panels = ['AUTH', 'DOCENTE', 'SUPERVISOR', 'ADMIN', 'COMITE'] as const;
  for (const panel of panels) {
    const data = (window as unknown as Record<string, unknown>)[`__GNF_${panel}__`] as InitData | undefined;
    if (data?.restUrl) {
      _init = data;
      return _init;
    }
  }

  // Fallback
  _init = {
    restUrl: '/wp-json/gnf/v1',
    nonce: '',
  };
  return _init;
}

export class ApiRequestError extends Error {
  code: string;
  status: number;

  constructor(error: ApiError) {
    super(error.message);
    this.name = 'ApiRequestError';
    this.code = error.code;
    this.status = error.data?.status ?? 500;
  }
}

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  signal?: AbortSignal,
): Promise<T> {
  const { restUrl, nonce } = getInit();
  const url = `${restUrl}${path}`;

  const headers: Record<string, string> = {};
  if (nonce) {
    headers['X-WP-Nonce'] = nonce;
  }

  const options: RequestInit = {
    method,
    headers,
    credentials: 'same-origin',
    signal,
  };

  if (body !== undefined && method !== 'GET') {
    headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(body);
  }

  const response = await fetch(url, options);

  if (!response.ok) {
    let error: ApiError;
    try {
      error = await response.json() as ApiError;
    } catch {
      error = {
        code: 'unknown_error',
        message: response.statusText,
        data: { status: response.status },
      };
    }
    throw new ApiRequestError(error);
  }

  // Handle 204 No Content
  if (response.status === 204) {
    return undefined as T;
  }

  return response.json() as Promise<T>;
}

export function get<T>(path: string, params?: Record<string, string | number | undefined>, signal?: AbortSignal): Promise<T> {
  const filteredParams: Record<string, string> = {};
  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== '') {
        filteredParams[key] = String(value);
      }
    }
  }
  const qs = new URLSearchParams(filteredParams).toString();
  const fullPath = qs ? `${path}?${qs}` : path;
  return request<T>('GET', fullPath, undefined, signal);
}

export function post<T>(path: string, body?: unknown): Promise<T> {
  return request<T>('POST', path, body);
}

export function put<T>(path: string, body?: unknown): Promise<T> {
  return request<T>('PUT', path, body);
}

export function del<T>(path: string): Promise<T> {
  return request<T>('DELETE', path);
}
