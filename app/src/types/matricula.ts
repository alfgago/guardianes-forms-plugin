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
