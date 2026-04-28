export interface MatriculaFieldCondition {
  field: string;
  operator: string;
  value: string;
}

export interface MatriculaFieldDefinition {
  key: string;
  name: string;
  label: string;
  type: string;
  instructions: string;
  required: boolean;
  choices: Record<string, string>;
  multiple: boolean;
  taxonomy?: string;
  returnFormat?: string;
  conditionalLogic: MatriculaFieldCondition[][];
}

export interface Matricula {
  id: number;
  centroId: number;
  userId: number;
  anio: number;
  metaEstrellas: number;
  retosSeleccionados: number[];
  estado: string;
  createdAt: string;
  updatedAt: string;
}

export interface MatriculaPrefill {
  centro: {
    id: number;
    nombre: string;
    codigoMep: string;
    direccion?: string;
    provincia?: string;
    canton?: string;
  } | null;
  retosDisponibles: MatriculaReto[];
  retosSeleccionados: number[];
  metaEstrellas: number;
  comiteEstudiantes?: number;
  prefill: MatriculaFormValues;
  fieldDefs: Record<string, MatriculaFieldDefinition>;
  choiceSets: {
    nivel_educativo: Record<string, string>;
    dependencia: Record<string, string>;
    jornada: Record<string, string>;
    tipologia: Record<string, string>;
    tipo_centro_educativo: Record<string, string>;
    coordinador_cargo: Record<string, string>;
    ultimo_anio_participacion: Record<string, string>;
    ultimo_galardon_estrellas: Record<string, string>;
  };
  provincias: string[];
  cantonesPorProvincia: Record<string, string[]>;
  regiones: Array<{ id: number; name: string }>;
}

export interface MatriculaReto {
  id: number;
  titulo: string;
  descripcion: string;
  iconUrl?: string;
  puntajeMaximo: number;
  obligatorio: boolean;
  hasForm: boolean;
}

export interface MatriculaFormValues {
  centroExiste: string;
  centroIdExistente: number;
  centroNombre: string;
  centroCodigoMep: string;
  centroCorreoInstitucional: string;
  centroTelefono: string;
  centroNivelEducativo: string;
  centroDependencia: string;
  centroJornada: string;
  centroTipologia: string;
  centroTipoCentroEducativo: string;
  centroRegion: number;
  centroCircuito: string;
  centroProvincia: string;
  centroCanton: string;
  centroCodigoPresupuestario: string;
  centroDireccion: string;
  centroTotalEstudiantes: number;
  centroEstudiantesHombres: number;
  centroEstudiantesMujeres: number;
  centroEstudiantesMigrantes: number;
  centroUltimoGalardonEstrellas: string;
  centroUltimoAnioParticipacion: string;
  centroUltimoAnioParticipacionOtro: string;
  coordinadorCargo: string;
  coordinadorNombre: string;
  coordinadorCelular: string;
  representanteNombre: string;
  representanteCargo: string;
  representanteTelefono: string;
  representanteEmail: string;
  representanteEmailConfirm: string;
  docenteConfirmaciones: string[];
  comiteEstudiantes: number;
  inscripcionAnterior: string;
  metaEstrellas: string;
}
