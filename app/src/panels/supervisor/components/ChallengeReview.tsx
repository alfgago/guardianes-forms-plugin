import { useEffect, useRef, useState } from 'react';
import { ArrowRight, ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { EntryReviewCard } from './EntryReviewCard';
import { evidenceAnchor, nextPending, reviewCounts, reviewFilters, type ReviewEntry, type ReviewFilter } from './reviewNavigation';
import './challenge-review.css';

export function ChallengeReview({ entries, centroId, year }: { entries: ReviewEntry[]; centroId: number; year: number }) {
  const storageKey = `gnf-review-reto-v1:${centroId}:${year}`;
  const [selected, setSelected] = useState(() => {
    try { return Number(sessionStorage.getItem(storageKey)); } catch { return 0; }
  });
  const [filter, setFilter] = useState<ReviewFilter>('all');
  const [editing, setEditing] = useState(false);
  const [target, setTarget] = useState<{ entryId: number; index: number; serial: number } | null>(null);
  const lastTarget = useRef({ retoId: 0, index: -1 });
  const tablist = useRef<HTMLDivElement>(null);
  const toolbar = useRef<HTMLDivElement>(null);
  const workspace = useRef<HTMLElement>(null);
  const entry = entries.find(item => item.retoId === selected) ?? entries[0];
  const counts = entry ? reviewCounts(entry) : null;
  const pending = entries.reduce((sum, item) => sum + reviewCounts(item).pendiente, 0);

  useEffect(() => {
    if (!entry) return;
    try { sessionStorage.setItem(storageKey, String(entry.retoId)); } catch { /* Navigation works without storage. */ }
    const tab = tablist.current?.querySelector<HTMLElement>('[aria-selected="true"]');
    if (tab && tablist.current) {
      const list = tablist.current;
      if (tab.offsetLeft < list.scrollLeft || tab.offsetLeft + tab.offsetWidth > list.scrollLeft + list.clientWidth) {
        list.scrollLeft = tab.offsetLeft - list.clientWidth / 2 + tab.offsetWidth / 2;
      }
    }
  }, [entry?.retoId, storageKey]);

  useEffect(() => {
    if (!toolbar.current || !workspace.current) return;
    const observer = new ResizeObserver(() => {
      workspace.current?.style.setProperty('--review-toolbar-height', `${toolbar.current?.offsetHeight ?? 0}px`);
    });
    observer.observe(toolbar.current);
    return () => observer.disconnect();
  }, [Boolean(entry)]);

  useEffect(() => {
    if (!target) return;
    const node = document.getElementById(evidenceAnchor(target.entryId, target.index));
    node?.focus({ preventScroll: true });
    node?.scrollIntoView({ block: 'start', behavior: 'instant' });
  }, [target]);

  function selectReto(id: number) {
    if (editing) return;
    setSelected(id);
    setFilter('all');
    lastTarget.current = { retoId: id, index: -1 };
    setTarget(null);
  }

  function goToNext() {
    if (!entry || editing) return;
    const next = nextPending(entries, entry.retoId, lastTarget.current.retoId === entry.retoId ? lastTarget.current.index : -1);
    if (!next) return;
    setSelected(next.retoId);
    setFilter('all');
    lastTarget.current = { retoId: next.retoId, index: next.index };
    setTarget({ entryId: next.entryId, index: next.index, serial: (target?.serial ?? 0) + 1 });
  }

  if (!entry || !counts) return <p>No hay retos matriculados para este centro.</p>;

  return (
    <section className="gnf-review-workspace" ref={workspace} aria-label="Revisión de retos">
      <div className="gnf-review-toolbar" ref={toolbar}>
        <div className="gnf-review-heading">
          <h3>Retos ({entries.length})</h3>
          <label className="gnf-review-selector">Todos los retos
            <select value={entry.retoId} disabled={editing} onChange={event => selectReto(Number(event.target.value))}>
              {entries.map(item => <option key={item.retoId} value={item.retoId}>{item.retoTitulo} ({reviewCounts(item).pendiente} por revisar)</option>)}
            </select>
          </label>
          <Button size="sm" variant="outline" icon={<ArrowRight size={16} />} onClick={goToNext} disabled={editing || pending === 0}>
            Siguiente pendiente{pending > 0 ? ` (${pending})` : ''}
          </Button>
        </div>
        <div className="gnf-review-tabrow">
          <button className="gnf-review-arrow" aria-label="Ver retos anteriores" title="Ver retos anteriores" onClick={() => tablist.current?.scrollBy({ left: -320 })}><ChevronLeft size={20} /></button>
          <div className="gnf-review-tabs" role="tablist" aria-label="Retos del centro" ref={tablist} onKeyDown={event => {
            const tabs = Array.from(tablist.current?.querySelectorAll<HTMLButtonElement>('[role="tab"]') ?? []);
            const index = tabs.indexOf(document.activeElement as HTMLButtonElement);
            if (index < 0 || editing) return;
            const next = event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowLeft' ? (index - 1 + tabs.length) % tabs.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : -1;
            if (next >= 0) { event.preventDefault(); tabs[next]?.focus(); tabs[next]?.click(); }
          }}>
            {entries.map(item => {
              const count = reviewCounts(item).pendiente;
              return <button key={item.retoId} id={`review-tab-${item.retoId}`} role="tab" aria-selected={item.retoId === entry.retoId} aria-controls={`review-panel-${centroId}`} tabIndex={item.retoId === entry.retoId ? 0 : -1} disabled={editing} onClick={() => selectReto(item.retoId)}>
                {item.retoIconUrl && <img src={item.retoIconUrl} alt="" width="24" height="24" />}
                <span>{item.retoTitulo}</span><span className="gnf-review-count" aria-label={`${count} por revisar`}>{count}</span>
              </button>;
            })}
          </div>
          <button className="gnf-review-arrow" aria-label="Ver más retos" title="Ver más retos" onClick={() => tablist.current?.scrollBy({ left: 320 })}><ChevronRight size={20} /></button>
        </div>
        <div className="gnf-review-filters" role="group" aria-label="Filtrar evidencias">
          {reviewFilters.map(item => <button key={item.value} data-status={item.value} aria-pressed={filter === item.value} disabled={editing} onClick={() => setFilter(item.value)}>{item.label} <strong>{counts[item.value]}</strong></button>)}
        </div>
        {editing && <p className="gnf-review-editing" role="status">Guarda o cancela el comentario para cambiar de reto o filtro.</p>}
      </div>
      <div id={`review-panel-${centroId}`} role="tabpanel" aria-labelledby={`review-tab-${entry.retoId}`} tabIndex={0} className="gnf-review-panel">
        <div className="gnf-review-title">
          {entry.retoIconUrl && <img src={entry.retoIconUrl} alt="" width="40" height="40" />}
          <h3>{entry.retoTitulo}</h3>
          <span><strong>{entry.puntaje} / {entry.puntajeMaximo}</strong> eco puntos reportados</span>
        </div>
        <EntryReviewCard key={entry.id + ':' + entry.retoId} entry={entry} filter={filter} onEditingChange={setEditing} />
      </div>
    </section>
  );
}
