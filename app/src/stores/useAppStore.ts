import { create } from 'zustand';

interface AppState {
  logoUrl: string;
  setLogoUrl: (url: string) => void;
}

export const useAppStore = create<AppState>((set) => ({
  logoUrl: '',
  setLogoUrl: (url) => set({ logoUrl: url }),
}));
