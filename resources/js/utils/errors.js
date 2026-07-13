export function errorMessage(error, fallback = 'No se pudo completar la accion.') {
  const data = error?.response?.data;
  if (data?.errors) return Object.values(data.errors).flat()[0];
  return data?.message || fallback;
}
