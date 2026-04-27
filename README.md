# Dispensador de Medicina

Aplicación web en **PHP** para administrar usuarios, medicamentos y programación de dispensos en un dispensador automático.

> Estado del proyecto: funcional para entorno académico/prototipo. Incluye APIs para integrar un microcontrolador o servicio externo que reporte estado y eventos de dispenso.

---

## Tabla de contenido

1. [Arquitectura y stack](#arquitectura-y-stack)
2. [Módulos principales](#módulos-principales)
3. [Requisitos](#requisitos)
4. [Instalación rápida (Docker)](#instalación-rápida-docker)
5. [Instalación manual (Apache + PHP)](#instalación-manual-apache--php)
6. [Configuración de variables de entorno](#configuración-de-variables-de-entorno)
7. [Base de datos y migraciones recomendadas](#base-de-datos-y-migraciones-recomendadas)
8. [Flujo de uso por rol](#flujo-de-uso-por-rol)
9. [APIs del sistema](#apis-del-sistema)
10. [Correo y notificaciones](#correo-y-notificaciones)
11. [Estructura del proyecto](#estructura-del-proyecto)
12. [Problemas frecuentes](#problemas-frecuentes)
13. [Seguridad y mejoras sugeridas](#seguridad-y-mejoras-sugeridas)

---

## Arquitectura y stack

- **Backend**: PHP 8.2 (PDO).
- **Base de datos en runtime**: **PostgreSQL** (DSN `pgsql`, compatible con Supabase).
- **Servidor web**: Apache.
- **Correo**: `mail()` de PHP (en Docker se enruta a `msmtp`).
- **Integración externa**: OpenFDA para catálogo de medicamentos.

⚠️ El archivo `dispensador_medicina.sql` es un dump legado de MySQL/MariaDB; el código actual está preparado para PostgreSQL, por lo que ese dump no se aplica directamente sin adaptación.

---

## Módulos principales

- **Autenticación**
  - Login clásico por usuario/contraseña.
  - Endpoint backend para login con Google ID Token.
- **Dashboard por rol**
  - `admin`: administración completa.
  - `cuidador`: gestión de pacientes/asignaciones y seguimiento.
  - `paciente`: consulta de sus próximos dispensos/historial.
- **Medicamentos**
  - Alta manual + autocompletado desde OpenFDA.
  - Filtro de formas comestibles (pastillas/cápsulas, etc.).
- **Programación de dispensos**
  - Registro de hora, cantidad, frecuencia y estado.
- **Historial y eventos**
  - Registro de resultado del dispenso (`exitoso`/`error`).
  - Envío opcional de correo al destinatario.
- **Asignaciones cuidador-paciente**
  - Modo moderno con tabla relacional.
  - Compatibilidad con modo legado si la migración no existe.

---

## Requisitos

- PHP 8.2+ con extensiones:
  - `pdo`
  - `pdo_pgsql`
  - `pgsql`
- PostgreSQL accesible (local o remoto).
- Apache/Nginx (o Docker).
- Internet para:
  - OpenFDA (`api.fda.gov`)
  - Validación de token Google (`oauth2.googleapis.com`), si se usa ese login.

---

## Instalación rápida (Docker)

### 1) Construir imagen

```bash
docker build -t dispensador-medicina .
```

### 2) Ejecutar contenedor

```bash
docker run --rm -p 8080:80 \
  -e DB_HOST=TU_HOST \
  -e DB_PORT=5432 \
  -e DB_NAME=TU_BD \
  -e DB_USER=TU_USUARIO \
  -e DB_PASSWORD=TU_PASSWORD \
  dispensador-medicina
```

### 3) Abrir en navegador

- `http://localhost:8080/`

> Si también quieres correos salientes en Docker, revisa la sección [Correo y notificaciones](#correo-y-notificaciones).

---

## Instalación manual (Apache + PHP)

1. Copia el proyecto al directorio público de Apache (ej. `htdocs/dispensador`).
2. Configura PHP con soporte PostgreSQL (`pdo_pgsql`).
3. Define variables de entorno de conexión (ver sección siguiente).
4. Reinicia Apache.
5. Abre:
   - `http://localhost/dispensador/`

---

## Configuración de variables de entorno

Variables de base de datos (obligatorias):

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`

Variables opcionales:

- `GOOGLE_CLIENT_ID` (valida que el token Google pertenezca a tu app).
- `MAIL_FROM` (remitente por defecto).
- `MAIL_FROM_NAME` (nombre del remitente).
- SMTP en Docker:
  - `SMTP_HOST`
  - `SMTP_PORT` (default típico: `587`)
  - `SMTP_FROM` (o `MAIL_FROM`)
  - `SMTP_USER`
  - `SMTP_PASS`

Ejemplo para Apache (`SetEnv`):

```apache
SetEnv DB_HOST "localhost"
SetEnv DB_PORT "5432"
SetEnv DB_NAME "dispensador_medicina"
SetEnv DB_USER "postgres"
SetEnv DB_PASSWORD "postgres"
SetEnv GOOGLE_CLIENT_ID "xxxx.apps.googleusercontent.com"
SetEnv MAIL_FROM "notificaciones@tu-dominio.com"
SetEnv MAIL_FROM_NAME "Dispensador"
```

---

## Base de datos y migraciones recomendadas

Para habilitar correctamente la vista por paciente en rol **cuidador**, ejecuta estas migraciones en PostgreSQL:

```sql
ALTER TABLE programacion
  ADD COLUMN IF NOT EXISTS id_paciente INT NULL,
  ADD COLUMN IF NOT EXISTS duracion_dias INT NULL;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'fk_programacion_paciente'
  ) THEN
    ALTER TABLE programacion
      ADD CONSTRAINT fk_programacion_paciente
      FOREIGN KEY (id_paciente) REFERENCES usuarios(id_usuario);
  END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_programacion_id_paciente
  ON programacion(id_paciente);

CREATE TABLE IF NOT EXISTS cuidadores_pacientes (
  id_relacion SERIAL PRIMARY KEY,
  id_cuidador INT NOT NULL,
  id_paciente INT NOT NULL,
  fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (id_cuidador, id_paciente),
  CONSTRAINT fk_cp_cuidador FOREIGN KEY (id_cuidador) REFERENCES usuarios(id_usuario),
  CONSTRAINT fk_cp_paciente FOREIGN KEY (id_paciente) REFERENCES usuarios(id_usuario)
);
```

Si no aplicas estas migraciones, el dashboard de cuidador funciona en **modo legado** (sin segmentación completa por paciente).

---

## Flujo de uso por rol

### Admin

- Crear usuarios.
- Crear/editar programación de medicamentos.
- Gestionar asignaciones cuidador-paciente.
- Configurar correos de notificación y remitentes.
- Ver historial global.

### Cuidador

- Consultar medicamentos.
- Programar medicamentos para pacientes asignados.
- Ver historial.
- Configurar correos propios y de pacientes asociados.

### Paciente

- Consultar próximos medicamentos.
- Ver su historial de dispensos.

---

## APIs del sistema

### `POST /api/registrar_dispenso.php`
Registra un evento de dispenso y dispara notificación por correo (si aplica).

**Parámetros**:
- `id_programacion`
- `resultado` (`exitoso` o `error`)
- `observaciones` (opcional)

### `GET /api/obtener_programacion.php`
Devuelve dispensos activos que coinciden con la hora actual.

### `POST /api/estado_dispositivo.php`
Marca el dispositivo como conectado y actualiza `ultimo_ping`.

### `GET /api/medicamentos_externos.php`
Consume OpenFDA y devuelve lista filtrada de medicamentos comestibles.

---

## Correo y notificaciones

### Funcionamiento

- Al registrar un dispenso, el sistema intenta notificar por correo al usuario objetivo.
- El destinatario se obtiene de:
  1. `usuarios.correo` (si existe y tiene valor), o
  2. `usuarios.usuario` si es un email válido.
- El remitente activo se obtiene de:
  1. tabla `configuracion_correos_salida` (si existe remitente activo), o
  2. variables `MAIL_FROM` / `MAIL_FROM_NAME`.

### Docker + SMTP

El contenedor genera `/etc/msmtprc` automáticamente si detecta:

- `SMTP_HOST`
- `SMTP_FROM` (o `MAIL_FROM`)
- `SMTP_USER`
- `SMTP_PASS`

Si falta cualquiera, el contenedor inicia pero **sin** configuración de envío saliente.

---

## Estructura del proyecto

```text
api/                 Endpoints para dispositivo e integraciones
asignaciones/        Gestión cuidador-paciente
auth/                Login, logout y Google token login
config/              Conexión a BD y notificaciones
dashboard/           Vista principal por rol
docker/              Entrypoint para SMTP/msmtp
historial/           Consulta de eventos de dispenso
medicamentos/        Alta/listado de medicamentos
programacion/        Alta de programación de dispensos
usuarios/            Alta de usuarios y configuración de correos
index.php            Pantalla de acceso
Dockerfile           Imagen PHP 8.2 + Apache + PostgreSQL extensions
```

---

## Problemas frecuentes

### Error de conexión a base de datos

- Verifica que todas las variables `DB_*` estén definidas.
- Confirma conectividad de red hacia PostgreSQL.
- Revisa credenciales/puerto.

### No carga medicamentos externos

- Verifica salida a internet desde tu servidor/contenedor.
- Prueba manualmente: `https://api.fda.gov/drug/ndc.json?limit=1`

### No se envían correos

- Revisa diagnóstico en `usuarios/configurar_correos.php`.
- En Docker, confirma variables SMTP y revisa `/tmp/msmtp.log`.
- Asegura que el destinatario tenga email válido.

### Google login devuelve token inválido

- Verifica que el `id_token` se esté enviando correctamente al backend.
- Si definiste `GOOGLE_CLIENT_ID`, el `aud` del token debe coincidir.

---

## Seguridad y mejoras sugeridas

Antes de producción, aplicar al menos:

- Protección CSRF en formularios.
- Políticas más estrictas de sesión/cookies.
- Validación y sanitización adicional en entradas.
- Gestión de secretos fuera de `SetEnv` plano.
- Auditoría de permisos por endpoint.
- Logs centralizados y monitoreo.

---

Si quieres, en un siguiente paso te puedo dejar también:

1. un **`docker-compose.yml`** con PostgreSQL + app,
2. un **script SQL de migración PostgreSQL completo** (desde cero),
3. y un **checklist de despliegue en Render/Railway**.
