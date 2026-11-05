export type SpecValue = string | number | boolean | string[] | null;
export type SpecField = { label: string; type: string; options: string[] };
export type SpecSchema = Record<string, Record<string, SpecField>>;
export const sourceLabels: Record<string, string> = {
    manual: 'Manual',
    icecat: 'Icecat',
    tabla: 'Tabla',
    nombre: 'Por nombre',
    supuesto: 'Estimado',
};
