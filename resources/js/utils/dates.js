// La API puede devolver fechas como "2026-10-05" o como "2026-10-05T00:00:00.000000Z".
// Se toma solo la parte de fecha y se fija al mediodía para evitar saltos por zona horaria.
export function parseDay(value) {
  if (!value) return null;
  const match = String(value).match(/^\d{4}-\d{2}-\d{2}/);
  const date = new Date(match ? `${match[0]}T12:00:00` : value);
  return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDay(value, options) {
  const date = parseDay(value);
  return date ? date.toLocaleDateString('es-GT', options) : '—';
}
