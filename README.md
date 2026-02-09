# Dispensador de Medicina (PHP + MySQL)

Aplicación web para gestionar usuarios, medicamentos y programación de dispensos.

Este README explica **desde cero** cómo levantar el proyecto con **XAMPP** en Windows y cómo usar las funciones principales, incluyendo login normal, login con Google y carga de medicamentos desde API externa (sin jarabes).

---

## 1) Requisitos

- XAMPP (Apache + MySQL + PHP)
- Navegador web
- Conexión a internet (para Google Login y API de medicamentos externos)

> Recomendado: PHP 8.0+ y MySQL/MariaDB incluidos en XAMPP.

---

## 2) Instalar el proyecto en XAMPP

1. Copia esta carpeta del proyecto dentro de:
   - `C:\xampp\htdocs\holaw`
2. Abre **XAMPP Control Panel**.
3. Inicia:
   - **Apache**
   - **MySQL**

---

## 3) Crear la base de datos

1. Abre `http://localhost/phpmyadmin`.
2. Crea una base de datos llamada:
   - `dispensador_medicina`
3. Importa el archivo SQL del proyecto:
   - `dispensador_medicina.sql`

---

## 4) Configurar credenciales de base de datos

La conexión usa variables de entorno (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`).

Si no defines variables, por defecto intentará:

- Host: `127.0.0.1`
- Puerto: `3306`
- Base de datos: `dispensador_medicina`
- Usuario: `root`
- Password: `""` (vacío)

En una instalación XAMPP típica, esto suele funcionar directamente.

---

## 5) (Opcional) Configurar Login con Google

Para que funcione el botón de Google:

1. Entra a Google Cloud Console.
2. Crea un proyecto o usa uno existente.
3. Configura OAuth y obtén tu **Client ID** de web.
4. Define la variable de entorno `GOOGLE_CLIENT_ID` en Apache/PHP.

### Opción rápida en XAMPP (Apache)

Puedes agregar en `httpd.conf` o en un VirtualHost:

```apache
SetEnv GOOGLE_CLIENT_ID "TU_CLIENT_ID.apps.googleusercontent.com"
```

Luego reinicia Apache.


### Checklist rápida para que aparezca y funcione el botón

1. En Google Cloud, crea credencial OAuth de tipo **Web application**.
2. En `Authorized JavaScript origins`, agrega: `http://localhost`.
3. Copia el Client ID y configúralo en Apache:

```apache
SetEnv GOOGLE_CLIENT_ID "TU_CLIENT_ID.apps.googleusercontent.com"
```

4. Reinicia Apache en XAMPP.
5. Entra a `http://localhost/holaw/` y prueba el botón de Google.

> Si no configuras `GOOGLE_CLIENT_ID`, el botón puede mostrarse pero la validación de audiencia del token en backend no tendrá el ID esperado.

---


## 5.1) Migración recomendada para cuidador/paciente (próximos medicamentos)

Para que el cuidador vea los próximos medicamentos **de sus pacientes** (no solo propios), agrega relación cuidador-paciente y paciente objetivo en programación.

Ejecuta en phpMyAdmin SQL:

```sql
ALTER TABLE programacion
  ADD COLUMN id_paciente INT NULL AFTER id_usuario,
  ADD INDEX idx_programacion_id_paciente (id_paciente),
  ADD CONSTRAINT fk_programacion_paciente
    FOREIGN KEY (id_paciente) REFERENCES usuarios(id_usuario);

CREATE TABLE IF NOT EXISTS cuidadores_pacientes (
  id_relacion INT AUTO_INCREMENT PRIMARY KEY,
  id_cuidador INT NOT NULL,
  id_paciente INT NOT NULL,
  fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cuidador_paciente (id_cuidador, id_paciente),
  CONSTRAINT fk_cp_cuidador FOREIGN KEY (id_cuidador) REFERENCES usuarios(id_usuario),
  CONSTRAINT fk_cp_paciente FOREIGN KEY (id_paciente) REFERENCES usuarios(id_usuario)
);
```

> Sin esta migración el sistema entra en modo legado: funciona, pero el cuidador ve su propia programación y no la de pacientes asignados.


### Opción sin phpMyAdmin (recomendada)

Ahora puedes hacer esta configuración desde la web como admin:

1. Inicia sesión como `admin`.
2. Ve a **Asignar pacientes a cuidadores**.
3. Si aparece aviso de migración, pulsa **Aplicar migración automáticamente**.
4. Selecciona cuidador y paciente, luego **Guardar asignación**.

Con eso ya no necesitas crear relaciones manualmente en phpMyAdmin.


## 6) Abrir el sistema

Con Apache y MySQL activos, entra a:

- `http://localhost/holaw/`

Pantalla inicial:
- Login tradicional (usuario/contraseña)
- Login con Google (si está configurado)

---

## 7) ¿Cómo usarlo?

### 7.1 Login tradicional

- Ingresa `usuario` y `contrasena` de un registro existente en tabla `usuarios`.
- Según el rol (`admin`, `cuidador`, `paciente`) redirige a su dashboard.

### 7.2 Login con Google

- Haz clic en el botón de Google.
- Si el correo no existe en `usuarios`, se crea automáticamente como rol `paciente`.
- Luego inicia sesión y entra al dashboard correspondiente.

### 7.3 Agregar medicamentos desde API externa (sin jarabes)

En la pantalla de agregar medicamentos:

1. Se carga un selector con datos desde OpenFDA.
2. El sistema filtra presentaciones tipo `syrup` / `jarabe`.
3. Al elegir un medicamento, autocompleta:
   - nombre
   - tipo
   - dosis
4. Completa `cantidad_total` y `fecha_vencimiento`.
5. Guarda.

---

## 8) Rutas importantes

- Inicio: `http://localhost/holaw/`
- Login clásico: `auth/validarlogin.php`
- Login Google (backend): `auth/google_login.php`
- API medicamentos externos: `api/medicamentos_externos.php`
- Agregar medicamentos: `medicamentos/agregar.php`

---

## 9) Solución de problemas

### No conecta a la base de datos

- Verifica que MySQL esté iniciado en XAMPP.
- Verifica nombre de BD: `dispensador_medicina`.
- Verifica usuario/password de MySQL.

### No carga medicamentos externos

- Verifica internet en el servidor local.
- Prueba abrir manualmente:
  - `https://api.fda.gov/drug/ndc.json?limit=100`

### Falla login con Google

- Confirma que `GOOGLE_CLIENT_ID` sea correcto.
- Verifica que el dominio/origen `http://localhost` esté autorizado en Google Cloud.
- Revisa consola del navegador para errores de OAuth.

---

## 10) Nota de seguridad (entorno local)

Este proyecto está orientado a entorno de desarrollo/local. Antes de producción se recomienda:

- Endurecer validaciones y sanitización
- Manejo robusto de logs/errores
- Configuración segura de sesiones y cookies
- Revisar políticas CORS/CSRF
- Usar HTTPS
