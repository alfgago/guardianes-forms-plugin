export interface Centro {
  id: number;
  nombre: string;
  codigoMep: string;
  regionId: number;
  regionName?: string;
  circuito?: string;
  direccion?: string;
  provincia?: string;
  canton?: string;
  telefono?: string;
  correoInstitucional?: string;
  codigoPresupuestario?: string;
  nivelEducativo?: string;
  dependencia?: string;
  jornada?: string;
  tipologia?: string;
  tipologiaLabel?: string;
  tipoCentroEducativo?: string;
  tipoCentroEducativoLabel?: string;
}

export interface AwardRequirement {
  key: string;
  label: string;
  met: boolean;
}

export interface AwardRecognition {
  key: string;
  label: string;
  achieved: boolean;
  requirements: AwardRequirement[];
  missing: string[];
}

export interface AwardResult {
  rubricKey: 'small' | 'general';
  rubricLabel: string;
  thresholds: Record<string, number>;
  score: number;
  scoreStars: number;
  stars: number;
  baseEligible: boolean;
  missingRequired: string[];
  awards: Record<string, AwardRecognition>;
  mode?: 'projected' | 'validated';
  generatedAt?: string;
  centroId?: number;
  year?: number;
}

export interface AwardBundle {
  assigned?: AssignedAward | null;
  projected: AwardResult;
  validated: AwardResult;
  rollout?: {
    mode: 'off' | 'pilot' | 'all';
    label: string;
    pilotCenterCount: number;
  };
  ruleVersion?: string;
}

export interface AssignedAward {
  result: AwardResult;
  assignedAt: string;
  assignedBy: number;
}

export interface CentroAnnualData {
  centroId: number;
  anio: number;
  metaEstrellas: number;
  puntajeTotal: number;
  estrellaFinal: number;
  retosSeleccionados: number[];
  comiteEstudiantes?: number;
  matriculaEstado: string;
  reportPdfUrl?: string;
  reportPdfStatus?: 'draft' | 'final';
  award?: AwardBundle;
}

export interface CentroWithStats extends Centro {
  annual: CentroAnnualData;
  retosCount: number;
  aprobados: number;
  enviados: number;
  correccion: number;
  enProgreso: number;
  evPendientes: number;
  evAprobadas: number;
  evRechazadas: number;
  evTotal: number;
  validado?: boolean;
  comiteStatus?: string;
  canImpersonateDocente?: boolean;
  docenteImpersonateUrl?: string;
}

export interface CentroSearchResult {
  id: number;
  nombre: string;
  codigoMep: string;
  regionId?: number;
  regionName?: string;
  claimed?: boolean;
  correoInstitucional?: string;
}
