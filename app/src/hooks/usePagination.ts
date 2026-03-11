import { useState, useMemo } from 'react';

interface PaginationResult<T> {
  page: number;
  perPage: number;
  totalPages: number;
  paginatedItems: T[];
  setPage: (page: number) => void;
  nextPage: () => void;
  prevPage: () => void;
}

export function usePagination<T>(items: T[], perPage = 10): PaginationResult<T> {
  const [page, setPage] = useState(1);

  const totalPages = Math.ceil(items.length / perPage);

  const paginatedItems = useMemo(
    () => items.slice((page - 1) * perPage, page * perPage),
    [items, page, perPage],
  );

  return {
    page,
    perPage,
    totalPages,
    paginatedItems,
    setPage: (p: number) => setPage(Math.max(1, Math.min(p, totalPages))),
    nextPage: () => setPage((p) => Math.min(p + 1, totalPages)),
    prevPage: () => setPage((p) => Math.max(p - 1, 1)),
  };
}
