import { create } from 'zustand';

interface YearState {
  activeYear: number;
  selectedYear: number;
  availableYears: number[];
  setSelectedYear: (year: number) => void;
  init: (activeYear: number, availableYears?: number[], selectedYear?: number) => void;
}

export const useYearStore = create<YearState>((set) => ({
  activeYear: new Date().getFullYear(),
  selectedYear: new Date().getFullYear(),
  availableYears: [new Date().getFullYear()],
  setSelectedYear: () => {
    set((state) => ({
      selectedYear: state.activeYear,
      availableYears: [state.activeYear],
    }));
  },
  init: (activeYear) => {
    const resolvedYear = activeYear || new Date().getFullYear();

    set({
      activeYear: resolvedYear,
      selectedYear: resolvedYear,
      availableYears: [resolvedYear],
    });
  },
}));
