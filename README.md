# Golden Path

Golden Path es un sistema web personal para mantener una rutina de gimnasio, registrar entrenamientos por serie, aplicar doble progresion explicable y representar la constancia mediante gamificacion ligera.

El producto es una SPA mobile-first para un solo propietario. No incluye registro publico, roles, pagos, comunidad, nutricion diaria ni servicios externos de IA.

> Este sistema es una herramienta personal de registro y motivacion. No sustituye la evaluacion de un medico, fisioterapeuta, nutricionista o entrenador certificado.

## Stack

- Laravel 12, PHP 8.3 y Sanctum.
- Vue 3, Composition API, Vue Router y Pinia.
- Bootstrap 5, SCSS y Font Awesome.
- Chart.js.
- MySQL 8.4.
- Nginx.
- Node.js 22.
- Docker Compose.
- PHPUnit.

## Requisitos

- Docker Desktop con Docker Compose v2.
- Al menos 2 GB de memoria libre para construir las imagenes.
- Puertos `8080`, `5173` y `3307` disponibles, o valores alternativos en `.env`.

No es necesario instalar PHP, Composer, Node o MySQL en el host.

## Variables principales

| Variable | Uso | Valor local predeterminado |
|---|---|---|
| `APP_URL` | URL publica de Laravel | `http://localhost:8080` |
| `APP_TIMEZONE` | Zona horaria de Laravel y contenedores | `America/Guatemala` |
| `APP_PORT` | Puerto web de Nginx | `8080` |
| `VITE_PORT` | Puerto del servidor Vite | `5173` |
| `DB_FORWARD_PORT` | Puerto MySQL expuesto al host | `3307` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexion de la aplicacion a MySQL | Deben configurarse en `.env` |
| `PERSONAL_USER_NAME`, `PERSONAL_USER_EMAIL`, `PERSONAL_USER_PASSWORD` | Propietario creado por el seeder | Deben configurarse antes del primer seed |

No publiques `.env` ni reutilices credenciales entre entornos.


## Instalacion desde cero

1. Crea la configuracion local:

```bash
cp .env.example .env
```

2. Define antes de levantar MySQL contrasenas aleatorias para `DB_PASSWORD`,
   `DB_ROOT_PASSWORD` y `PERSONAL_USER_PASSWORD`. Configura tambien el nombre y
   correo del propietario:

```dotenv
PERSONAL_USER_NAME=DerNait
PERSONAL_USER_EMAIL=owner@example.com
PERSONAL_USER_PASSWORD=<contrasena-local-aleatoria>
```

3. Construye y levanta los servicios:

```bash
docker compose up -d --build
```

4. Genera una clave si `APP_KEY` esta vacia:

```bash
docker compose exec app php artisan key:generate
```

5. Crea la base y los datos iniciales:

```bash
docker compose exec app php artisan migrate --seed
```

6. Publica las imagenes de ejercicios:

```bash
docker compose exec app php artisan storage:link
```

La aplicacion queda disponible en [http://localhost:8080](http://localhost:8080).

## Servicios y puertos

| Servicio | Funcion | Puerto predeterminado |
|---|---|---:|
| `nginx` | Aplicacion web | `8080` |
| `app` | PHP-FPM | interno `9000` |
| `mysql` | Base de datos | `3307` -> `3306` |
| `node` | Vite en desarrollo | `5173` |

Se pueden cambiar con `APP_PORT`, `DB_FORWARD_PORT` y `VITE_PORT`.

## Uso diario

```bash
docker compose up -d
docker compose ps
docker compose logs -f app nginx node
docker compose down
```

`docker compose down` conserva los volumenes de MySQL, Composer y npm. No uses `docker compose down -v` salvo que quieras eliminar todos los datos locales.

## Frontend

El servicio `node` ejecuta Vite con Node 22:

```bash
docker compose up -d node
docker compose logs -f node
```

Build local del frontend:

```bash
docker compose run --rm node sh -c "npm ci && npm run build"
```

Los archivos generados se guardan en `public/build`.

## Pruebas

La suite usa SQLite en memoria, separado del MySQL de desarrollo:

```bash
docker compose exec app php artisan test
```

Comprobaciones adicionales:

```bash
docker compose exec app php artisan route:list --path=api
docker compose exec app php artisan migrate:status
docker compose config --quiet
```

Los flujos E2E son una comprobacion de QA opcional y no son necesarios para levantar la aplicacion. Requieren Node 20 o 22 y Chromium de Playwright en el host o WSL:

```bash
npm install
npx playwright install --with-deps chromium
BASE_URL=http://localhost:8080 E2E_EMAIL=owner@example.com E2E_PASSWORD='<contrasena>' OUTPUT_DIR=storage/app/qa npm run test:e2e:smoke
BASE_URL=http://localhost:8080 E2E_EMAIL=owner@example.com E2E_PASSWORD='<contrasena>' OUTPUT_DIR=storage/app/qa npm run test:e2e:workout
```

El flujo de entrenamiento modifica los datos sembrados. Para devolver el entorno de desarrollo a su estado inicial, usa `migrate:fresh --seed` solamente si puedes borrar esos datos locales.

## Usuario personal

El seeder crea exactamente un propietario con las variables `PERSONAL_USER_*`. Para cambiar las credenciales iniciales antes de instalar, modifica `.env` y ejecuta `migrate:fresh --seed` solo si puedes borrar la base local:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Para cambiar la contrasena sin borrar datos, usa `Mi perfil > Cambiar contrasena`.

No existe registro publico, recuperacion de contrasena ni panel de usuarios.

## Datos iniciales

Los seeders crean:

- Perfil DerNait, 22 anos, 169.5 cm y peso inicial aproximado de 58.97 kg.
- Cintura y sexo biologico sin inventar, con valor `null`.
- Fase de recomposicion de 12 semanas, 48 sesiones y meta minima de 40.
- Rutina Upper/Lower con cuatro entrenamientos y tres descansos.
- 27 ejercicios planificados y sus alternativas.
- Todos los pesos objetivo en `null`, visibles como `Por calibrar`.
- Cinco fases del avatar.
- Catorce logros.
- Ninguna sesion de entrenamiento falsa.

## Rutina inicial

| Dia | Plan |
|---|---|
| Lunes | Upper A |
| Martes | Lower A |
| Miercoles | Descanso |
| Jueves | Upper B |
| Viernes | Lower B |
| Sabado | Descanso |
| Domingo | Descanso |

Los entrenamientos duran entre 60 y 85 minutos, con maximo orientativo de 90. El modo rapido mantiene ejercicios esenciales y oculta opcionales sin modificar el plan.

## Motor de progresion

`ProgressionService` consulta exclusivamente exposiciones del ejercicio realizado:

1. Las primeras dos exposiciones son calibracion.
2. Dentro del rango mantiene carga y guarda una meta explicita de una o dos repeticiones totales adicionales para la siguiente exposicion.
3. Al dominar todas las series solo recomienda el siguiente incremento cuando todas tienen RIR registrado, el RIR promedio es suficiente y la carga es consistente.
4. Una sesion mala aislada no reduce carga.
5. Dos exposiciones comparables bajo rango pueden recomendar una reduccion conservadora.
6. Una caida fuerte con descanso corto prioriza aumentar descanso.
7. Cuatro exposiciones sin mejora generan revision manual; una descarga solo se sugiere si tambien hay senales repetidas de recuperacion baja.
8. Sueno, energia, motivacion, dificultad, tecnica y molestias reducen la confianza o bloquean aumentos automaticos cuando corresponde.
9. Una sesion atipica reduce confianza y nunca basta por si sola para bajar carga.
10. Al generarse una recomendacion nueva, las pendientes anteriores del mismo ejercicio quedan reemplazadas y ya no pueden aplicarse.

Las recomendaciones requieren aceptar, ignorar o modificar. Las sesiones pasadas nunca cambian.

## Calculos

- Volumen: `peso x repeticiones` en series efectivas completadas.
- 1RM estimado: Epley, `peso x (1 + repeticiones / 30)`.
- Nivel: XP acumulada para nivel N = `100 x N x (N - 1) / 2`.
- Energia: 70% adherencia de 14 dias y 30% cercania al ultimo entrenamiento.
- Poder: 60% constancia de 28 dias, 30% progreso y 10% racha.
- Semana perfecta: cuatro entrenamientos completos.

Los calentamientos no cuentan para volumen, progresion, records, XP ni series efectivas.

## Gamificacion

- XP por sesiones completas o parciales validas, records, meta semanal, mediciones y logros.
- `event_key` unico evita recompensas repetidas.
- La fase maxima desbloqueada es permanente.
- La fase activa depende de energia reciente y puede bajar temporalmente.
- El avatar SVG original representa disciplina, no una medicion corporal cientifica.

## Imagenes de ejercicios

La biblioteca acepta `jpg`, `jpeg`, `png` y `webp` hasta 4 MB. Los archivos se guardan en `storage/app/public/exercises`.

Para reemplazar una imagen, edita el ejercicio y selecciona un archivo nuevo. El archivo anterior se elimina de forma segura. Para quitarla, usa el endpoint o control de eliminacion correspondiente.

Si una imagen no aparece:

```bash
docker compose exec app php artisan storage:link
docker compose exec app php artisan config:clear
```


## Videos de referencia

Desde `Rutina > Biblioteca`, edita un ejercicio y pega un enlace HTTPS de YouTube en `Video de referencia de YouTube`. Se aceptan enlaces `watch`, `youtu.be`, `shorts` y `embed`; el sistema los normaliza y rechaza otros dominios.

En la vista semanal y durante el entrenamiento activo, el boton con icono de ojo abre la ficha del ejercicio con sus datos, imagen y reproductor. Si existe video, tambien aparece `Abrir en YouTube` como respaldo.

El enlace es opcional y los ejercicios sembrados comienzan sin video.
## Fases de entrenamiento

Las fases de entrenamiento se administran mediante la API. Solo debe existir una fase activa:

```text
POST /api/training-phases
POST /api/training-phases/{id}/activate
POST /api/training-phases/{id}/complete
```

Completar una fase no elimina sesiones ni XP. Una nueva fase puede reutilizar la rutina activa.

## Arquitectura

```text
app/
  Enums/
  Http/Controllers/Api/
  Http/Requests/
  Http/Resources/
  Models/
  Policies/
  Services/Gamification/
  Services/Progression/
  Services/Workouts/
resources/js/
  api/
  components/
  layouts/
  router/
  stores/
  utils/
  views/
```

Las operaciones de inicio y finalizacion usan transacciones. Las sesiones guardan snapshots JSON de la rutina. Los controladores validan propiedad mediante Policies o consultas acotadas al usuario.

## Seguridad

- Sanctum con cookies de sesion y CSRF.
- Login limitado a cinco intentos por minuto.
- Rutas protegidas y redireccion al expirar la sesion.
- Form Requests para entradas complejas.
- Policies para recursos con propietario.
- MIME real y limite de tamano para imagenes.
- Password hashing de Laravel.
- Validacion de alternativas y unidades.
- Claves idempotentes para XP.

En produccion configura `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`, credenciales fuertes y un `SANCTUM_STATEFUL_DOMAINS` exacto.

## Produccion en gym.dernait.com

Produccion usa `docker-compose.production.yml`: PHP-FPM, Nginx interno publicado
solo en `127.0.0.1:8320` y MySQL sin puerto del host. No existe servicio Node o
Vite en el VPS. Los datos persistentes quedan en `/var/www/golden-path/shared` y
el volumen `golden-path-mysql-data`.

Prepara frontend, dependencias PHP e imagen `linux/amd64` en la maquina local:

```bash
./scripts/prepare-production.sh 20260712-1
```

Despliega el release preparado por SSH y `rsync` (el puerto predeterminado del
servidor es 17):

```bash
SSH_TARGET=root@167.233.42.11 SSH_PORT=17 ./scripts/deploy-production.sh 20260712-1
```

En el primer despliegue el script crea `.env` con permisos `0600`, genera todos
los secretos y muestra una sola vez la contrasena temporal del propietario. Si
el archivo ya existe, no modifica sus secretos. Para configurar el virtual host
y el certificado una vez que los contenedores respondan:

```bash
ssh -p 17 root@167.233.42.11 /var/www/golden-path/current/scripts/configure-host-nginx.sh
```

El script valida Nginx antes de recargarlo y Certbot configura la redireccion a
HTTPS. La plantilla sólo agrega el sitio `gym.dernait.com`; no altera los otros
virtual hosts de forma deliberada.

Variables y secretos de produccion se documentan sin valores en
`deploy/production.env.example`. Nunca copies ese ejemplo encima de un `.env`
existente.

## Respaldo y restauracion de MySQL

El despliegue instala una tarea diaria a las 03:17 UTC. Conserva siete dias de
respaldos cifrables por permisos de sistema en `/var/backups/golden-path`: un
volcado MySQL y un archivo de las imagenes. Prueba manual:

```bash
sudo /var/www/golden-path/current/scripts/backup-production.sh
```

Para restaurar, detente y revisa primero el destino. El volcado puede enviarse
al cliente MySQL usando las credenciales que ya existen dentro del contenedor,
sin imprimirlas en la terminal:

```bash
gzip -dc /var/backups/golden-path/mysql-AAAAMMDDTHHMMSSZ.sql.gz | \
  docker exec -i golden-path-mysql sh -c 'exec mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
```

Protege el archivo porque puede contener datos personales y notas de entrenamiento.

## Reinicio y solucion de problemas

Recrear contenedores sin borrar datos:

```bash
docker compose down
docker compose up -d --build
```

Limpiar caches de Laravel:

```bash
docker compose exec app php artisan optimize:clear
```

Revisar estado y logs:

```bash
docker compose ps
docker compose logs --tail=200 app nginx mysql node
```

Si el puerto esta ocupado, cambia el valor correspondiente en `.env`. Si MySQL no esta saludable, revisa que `DB_PASSWORD` coincida y que el volumen no provenga de una configuracion anterior.

## Limitaciones del MVP

- Es una aplicacion web, no una aplicacion movil nativa.
- No integra relojes, Apple Health, Google Fit ni notificaciones push.
- No registra comidas, calorias, fotos corporales ni cardio avanzado.
- Las recomendaciones son reglas deterministas, no diagnosticos ni IA.
- El 1RM estimado pierde precision con repeticiones altas.
- El volumen solo es comparable de forma util dentro del mismo ejercicio.
- La calidad de las recomendaciones depende de registrar peso, repeticiones, RIR y descanso con consistencia.

## Investigacion utilizada

Se reviso completamente `deep-research-report.md`. Las decisiones aplicadas se documentan en [docs/research-summary.md](docs/research-summary.md).
