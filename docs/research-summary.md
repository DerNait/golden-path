# Resumen tecnico de la investigacion

## Documento revisado

El documento original revisado completamente fue `deep-research-report.md`, ubicado en la raiz del proyecto. Se conserva sin moverlo ni sobrescribirlo.

`instrucciones.md` sigue siendo la fuente de verdad para el alcance del MVP y para la rutina exacta cargada por los seeders. La investigacion se uso para enriquecer decisiones, textos y reglas, no para ampliar el producto fuera de ese alcance.

## Decisiones incorporadas

### Objetivo y fase inicial

- El objetivo es recomposicion corporal, no una perdida agresiva de peso.
- La fase inicial dura 12 semanas y contempla 48 sesiones, con una meta minima de 40.
- El rango de peso de 58 a 60 kg es orientativo. No es una garantia ni el unico indicador de exito.
- La cintura, el rendimiento y la adherencia son las metricas primarias.
- El peso corporal, el volumen, el sueno y la energia son metricas complementarias.
- La cintura inicial permanece pendiente hasta que el usuario la registre.
- No se calcula porcentaje de grasa, mantenimiento calorico ni diagnostico medico.

### Rutina

- Se usa Upper/Lower cuatro dias por semana: lunes, martes, jueves y viernes.
- Miercoles, sabado y domingo son descansos planificados.
- Las sesiones tienen una duracion objetivo de 60 a 85 minutos y un maximo de 90.
- Los compuestos trabajan principalmente en rangos de 6-10 u 8-12 repeticiones.
- Los accesorios trabajan principalmente en 10-15 o 12-20 repeticiones.
- El RIR general es 1-2.
- Los compuestos tienen descansos de 120-180 segundos y los accesorios de 60-90 segundos.
- El modo rapido conserva ejercicios esenciales y oculta opcionales sin crear otra rutina.

### Progresion

- Las primeras dos exposiciones validas son calibracion y no inventan cargas iniciales.
- Cada `target_weight` comienza en `null` y la interfaz muestra `Por calibrar`.
- La doble progresion conserva la carga mientras aumentan repeticiones dentro del rango.
- Se recomienda aumentar carga cuando se completan todas las series en la meta, con RIR promedio de al menos 1 y sin una sesion atipica.
- Dentro del rango se sugieren una o dos repeticiones totales adicionales, sin superar el maximo posible.
- Una sola exposicion por debajo del rango no provoca una reduccion.
- Dos exposiciones malas pueden sugerir reducir entre 5% y 7%.
- Una caida fuerte entre series con descanso corto prioriza agregar 15-30 segundos antes de reducir peso.
- Cuatro exposiciones sin mejora clara activan una advertencia de posible estancamiento.
- Las recomendaciones son deterministas, explicables y requieren aceptar, ignorar o modificar.

### Alternativas e historial

- Una alternativa se relaciona con el ejercicio planificado solo para disponibilidad y navegacion.
- La carga, el volumen, las exposiciones y las recomendaciones pertenecen al ejercicio realizado.
- Nunca se copia automaticamente el peso del ejercicio principal a una alternativa.
- Las sesiones guardan un snapshot JSON para que una edicion posterior de la rutina no cambie el historial.

### Metricas

- El volumen efectivo es `peso x repeticiones` dentro del mismo ejercicio.
- Las series de calentamiento no cuentan para volumen, progresion, records, XP ni series efectivas.
- El 1RM estimado usa Epley: `peso x (1 + repeticiones / 30)`.
- La estimacion de 1RM se omite cuando no corresponde y se considera menos precisa sobre 15 repeticiones.
- Se registran peso corporal y cintura; no se incluyen fotografias corporales.

### Recuperacion

- La proteina de 100-130 g diarios y el sueno de 7 o mas horas se muestran como referencias informativas.
- Sueno, energia, motivacion y molestias son opcionales al iniciar una sesion.
- Una sesion atipica se conserva y reduce la confianza de la recomendacion.

### Gamificacion

- El avatar representa disciplina y constancia, no masa muscular real.
- La racha es semanal y exige cuatro sesiones completas.
- La energia combina adherencia de 14 dias y cercania al ultimo entrenamiento.
- El poder combina constancia reciente, progreso por ejercicio y racha.
- La fase maxima desbloqueada es permanente; la fase activa puede bajar temporalmente con la energia.
- Los eventos de XP tienen claves unicas para impedir recompensas duplicadas.
- No se recompensa crear sesiones vacias, repetir guardados ni saltarse descansos.

## Limite de responsabilidad

Golden Path es una herramienta personal de registro y motivacion. No sustituye la evaluacion de un medico, fisioterapeuta, nutricionista o entrenador certificado.
