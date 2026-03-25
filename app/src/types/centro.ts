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
  tipoCentroEducativo?: string;
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
}

export interface CentroWithStats extends Centro {
  annual: CentroAnnualData;
  retosCount: number;
  aprobados: number;
  enviados: number;
  correccion: number;
  enProgreso: number;
  validado?: boolean;
  comiteStatus?: string;
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
