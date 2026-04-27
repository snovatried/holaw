# Dispensador de Medicina

Aplicación web en **PHP** para administrar usuarios, medicamentos y programación de dispensos en un dispensador automático.

> Proyecto preparado para desplegarse en **Render** con **PostgreSQL (Supabase)**.

---

## Tabla de contenido

1. [Stack y arquitectura](#stack-y-arquitectura)
2. [Módulos del sistema](#módulos-del-sistema)
3. [Requisitos](#requisitos)
4. [Despliegue en Render (recomendado)](#despliegue-en-render-recomendado)
5. [Estado de base de datos](#estado-de-base-de-datos)
6. [Uso por roles](#uso-por-roles)
7. [APIs disponibles](#apis-disponibles)
8. [Notificaciones por correo](#notificaciones-por-correo)
9. [Estructura del proyecto](#estructura-del-proyecto)
10. [Troubleshooting](#troubleshooting)
11. [Seguridad y siguientes mejoras](#seguridad-y-siguientes-mejoras)

---

## Stack y arquitectura

- **Backend:** PHP 8.2 + PDO.
- **Base de datos runtime:** PostgreSQL (Supabase).
- **Servidor web:** Apache (imagen `php:8.2-apache`).
- **Correo:** `mail()` de PHP + `msmtp` en contenedor.
- **API externa:** OpenFDA para catálogo de medicamentos.

⚠️ El archivo `dispensador_medicina.sql` es un dump legado de MySQL/MariaDB y se conserva solo como referencia histórica.

---

## Módulos del sistema

- **Autenticación local:** usuario + contraseña.
- **Dashboard por rol:** `admin`, `cuidador`, `paciente`.
- **Medicamentos:** alta manual y autocompletado desde OpenFDA (solo formas comestibles).
- **Programación:** hora, cantidad, frecuencia, estado y duración.
- **Historial:** registro de eventos de dispenso (`exitoso`/`error`).
- **Asignaciones:** relación cuidador-paciente.
- **Correos:** configuración de destinatarios y remitente activo.

---

## Requisitos

- Cuenta en **Render**.
- Proyecto en repositorio Git conectado a Render.
- Base de datos PostgreSQL en Supabase ya vinculada al servicio.
- Variables de entorno ya configuradas en Render.
- Salida a internet para `api.fda.gov` (OpenFDA).

---

## Despliegue en Render (recomendado)

1. Sube este repositorio a GitHub/GitLab.
2. En Render, crea o reutiliza un **Web Service** desde ese repo.
3. Configura:
   - **Runtime:** Docker
   - **Port:** `80`
4. Verifica conexión con Supabase y variables del servicio en Render.
5. Haz deploy.

Al terminar, Render te dará una URL pública (`https://tu-app.onrender.com`).

---

## Estado de base de datos

Los cambios SQL de esquema ya están aplicados en la base de Supabase usada por Render.

- No es necesario ejecutar migraciones manuales al levantar nuevas instancias del servicio en Render.
- Las instancias apuntan a la misma base de datos gestionada, por lo que ya heredan esos cambios.

---

## Uso por roles

### Admin
- Crear usuarios.
- Gestionar asignaciones cuidador-paciente.
- Programar dispensos.
- Configurar correos y remitentes.
- Ver historial general.

### Cuidador
- Ver medicamentos.
- Programar para pacientes asignados.
- Ver historial.
- Configurar correos de pacientes vinculados.

### Paciente
- Ver próximos dispensos.
- Consultar historial propio.

---

## APIs disponibles

### `POST /api/registrar_dispenso.php`
Registra un evento de dispenso y dispara notificación por correo.

Parámetros:
- `id_programacion`
- `resultado` (`exitoso` o `error`)
- `observaciones` (opcional)

### `GET /api/obtener_programacion.php`
Entrega dispensos activos que coinciden con la hora actual.

### `POST /api/estado_dispositivo.php`
Marca el dispositivo como conectado y actualiza `ultimo_ping`.

### `GET /api/medicamentos_externos.php`
Consulta OpenFDA y devuelve medicamentos filtrados para el dispensador.

---

## Notificaciones por correo

- Al registrar un dispenso, el sistema intenta enviar correo al usuario objetivo.
- Destinatario:
  1. `usuarios.correo` (si existe y válido), o
  2. `usuarios.usuario` si contiene email válido.
- Remitente:
  1. remitente activo en `configuracion_correos_salida`, o
  2. configuración de correo definida en entorno.

Si no hay transporte SMTP funcional, la app sigue operando pero sin envío de correos.

---

## Estructura del proyecto

```text
api/                 Endpoints para dispositivo e integraciones
auth/                Login y logout
asignaciones/        Gestión cuidador-paciente
config/              Conexión PostgreSQL y notificaciones
dashboard/           Vista principal por rol
docker/              Entrypoint SMTP/msmtp
historial/           Consulta de eventos de dispenso
medicamentos/        Alta/listado/autocompletado
programacion/        Programación de dispensos
usuarios/            Alta de usuarios y correos
index.php            Pantalla de acceso
Dockerfile           Imagen base para Render
```

---

## Troubleshooting

### Error de conexión a base de datos
- Revisa configuración de conexión Supabase en Render.
- Verifica host/puerto y credenciales.
- Confirma que la base acepte conexiones desde el servicio.

### No carga OpenFDA
- Verifica salida a internet del servicio.
- Prueba `https://api.fda.gov/drug/ndc.json?limit=1`.

### No se envían correos
- Revisa configuración SMTP del servicio.
- Verifica remitente activo y destino válido.
- Consulta el diagnóstico en `usuarios/configurar_correos.php`.

---

## Seguridad y siguientes mejoras

Antes de producción real, se recomienda:

- CSRF tokens en formularios.
- Políticas de sesión/cookies más estrictas.
- Auditoría de permisos por endpoint.
- Logs centralizados y alertas.
- Gestión de secretos con rotación.
- Hardening adicional de validación de entrada.
