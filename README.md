# RemindMe

Aplicación para guardar y organizar recuerdos personales con imágenes, etiquetas, mood color y música de Spotify.

## Requisitos

- PHP 8.1+ con extensiones: `intl`, `mysqli`, `fileinfo`, `mbstring`, `openssl`
- MySQL 5.7+ o MariaDB
- Node.js 18+
- Composer

## Instalación

### Backend (CodeIgniter 4)

1. Entra en la carpeta del backend:
   ```
   cd backend
   ```

2. Instala dependencias PHP:
   ```
   composer install
   ```

3. Crea una base de datos vacía en MySQL:
   ```sql
   CREATE DATABASE nombre_de_tu_base_de_datos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

4. Copia el archivo de entorno:
   ```
   cp env .env
   ```
   Edita `.env` con tus valores:

   **Base de datos** — pon el nombre de la BD que creaste en el paso anterior:
   ```
   database.default.hostname = localhost
   database.default.database = nombre_de_tu_base_de_datos
   database.default.username = tu_usuario (default: root)
   database.default.password = tu_contraseña 
   ```

   **JWT secret** — genera una clave segura con este comando y pégala:
   ```
   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   ```
   ```
   auth.jwtSecret = (resultado del comando anterior)
   ```

   **Groq API key** *(opcional)* — necesaria solo para las sugerencias de IA (mejorar título, mejorar texto, sugerir etiquetas). Sin ella la app funciona con normalidad. Puedes obtener una clave gratuita en [console.groq.com](https://console.groq.com):
   1. Crea una cuenta o inicia sesión den la pagina oficial.
   2. Tras el registro se te redirigirá al /home y verás un dashboard con información y distintos enlaces en la esquina superior derecha.
   3. Accede a API Keys
   4. Pulsa en + Create API Key
   5. Introduce cualquier nombre (ejemplo: Remindme), deja el campo Expiration como viene (No expiration) y pulsa SUBMIT.
   6. Copia la API key e introducela en el .env.
   ```
   groq.apiKey = tu_api_key_de_groq
   ```

5. Ejecuta las migraciones para crear todas las tablas automáticamente (no es necesario importar ningún archivo SQL):
   ```
   php spark migrate
   ```

6. (Opcional) Carga la cuenta de demo con recuerdos de ejemplo:
   ```
   php spark db:seed DemoSeeder
   ```
   Esto crea el usuario `demo@remindme.com` con contraseña `Demo1234!` y 8 recuerdos de ejemplo con imágenes, tags y canciones de Spotify.

7. Inicia el servidor:
   ```
   php -S localhost:8080 -t public public/router.php
   ```

### Frontend (Angular)

1. Entra en la carpeta del frontend:
   ```
   cd frontend
   ```

2. Instala dependencias:
   ```
   npm install
   ```

3. Inicia la app:
   ```
   npm start
   ```

La aplicación estará disponible en `http://localhost:4200`.

## Cuenta de demo

| Campo      | Valor                 |
|------------|-----------------------|
| Email      | demo@remindme.com     |
| Contraseña | Demo1234!             |

## API — Endpoints principales

| Método | Ruta                        | Descripción            |
|--------|-----------------------------|------------------------|
| POST   | /api/v1/auth/register       | Registro               |
| POST   | /api/v1/auth/login          | Login                  |
| POST   | /api/v1/auth/refresh        | Refrescar token        |
| POST   | /api/v1/auth/logout         | Logout                 |
| GET    | /api/v1/auth/me             | Usuario autenticado    |
| GET    | /api/v1/memories            | Listar recuerdos       |
| POST   | /api/v1/memories            | Crear recuerdo         |
| GET    | /api/v1/memories/{id}       | Detalle de recuerdo    |
| POST   | /api/v1/memories/{id}       | Actualizar recuerdo    |
| DELETE | /api/v1/memories/{id}       | Eliminar recuerdo      |
