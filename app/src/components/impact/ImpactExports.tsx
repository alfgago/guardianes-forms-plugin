import { FileSpreadsheet, FileText } from 'lucide-react';
import type { ReportExports } from '@/api/reports';

const datasets = [
  { key: 'indicators', label: 'Indicadores XLSX' },
  { key: 'centros', label: 'Centros XLSX' },
  { key: 'retos', label: 'Retos XLSX' },
  { key: 'pdfSummary', label: 'PDF ejecutivo' },
  { key: 'pdfFull', label: 'PDF completo' },
] as const;

export function ImpactExports({ exports, enabled }: { exports?: ReportExports | null; enabled: boolean }) {
  return <div className="gnf-impact-exports" role="group" aria-label="Descargar reportes">
    {datasets.map(({ key, label }) => {
      const url = enabled ? exports?.[key] : null;
      const icon = key.startsWith('pdf') ? <FileText size={17} aria-hidden="true" /> : <FileSpreadsheet size={17} aria-hidden="true" />;
      return url
        ? <a className="gnf-impact-download" key={key} href={url} target="_blank" rel="noopener noreferrer">{icon}{label}</a>
        : <button className="gnf-impact-download" key={key} type="button" disabled>{icon}{label}</button>;
    })}
  </div>;
}
