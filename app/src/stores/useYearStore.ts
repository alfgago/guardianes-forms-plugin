import { create } from 'zustand';

interface YearState {
  activeYear: number;
  selectedYear: number;
  availableYears: number[];
  setSelectedYear: (year: number) => void;
  init: (activeYear: number, availableYears?: number[]) => void;
}

export const useYearStore = create<YearState>((set) => ({
  activeYear: new Date().getFullYear(),
  selectedYear: new Date().getFullYear(),
  availableYears: [new Date().getFullYear()],
  setSelectedYear: (year) => set({ selectedYear: year }),
  init: (activeYear, availableYears) =>
    set({
      activeYear,
      selectedYear: activeYear,
      availableYears: availableYears ?? [activeYear],
    }),
}));
