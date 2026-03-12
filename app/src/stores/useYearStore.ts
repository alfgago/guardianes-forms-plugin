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
  setSelectedYear: (year) => set({ selectedYear: year }),
  init: (activeYear, availableYears, selectedYear) => {
    const years = availableYears && availableYears.length > 0 ? availableYears : [activeYear];
    const resolvedYear = selectedYear && years.includes(selectedYear) ? selectedYear : activeYear;

    set({
      activeYear,
      selectedYear: resolvedYear,
      availableYears: years,
    });
  },
}));
