# Sistema de gestión de préstamo de equipos

Prototipo del Proyecto Final Integrador de la asignatura Desarrollo de Aplicaciones Empresariales. Grupo 5: Néstor David García Rondón y Juan Esteban Hernández Esparza.

Permite controlar la solicitud, el préstamo y la devolución de equipos y salones, con cuatro tipos de usuario: administrador, funcionario, docente y estudiante.

**Estado actual: esqueleto.** Funcionan el inicio de sesión, el acceso por rol, el 2FA opcional y la gestión de usuarios. Los demás módulos ya tienen su estructura, pero aún no sus funciones. La dirección principal muestra una portada provisional «en construcción»; el acceso está en `/login`.

## Requisitos

- PHP 8.2 o superior, con las extensiones `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `dom` y `xml`
- Composer
- Node.js y npm
- MariaDB 10.4 o superior (por ejemplo, la de XAMPP)

## Instalación

1. Instalar las dependencias:

   ```bash
   composer install
   npm install
   npm run build
   ```

2. Crear el archivo de configuración y la clave de la aplicación:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Si el usuario de MariaDB tiene contraseña, escribirla en `DB_PASSWORD` dentro de `.env`.

3. Con MariaDB encendida, crear las dos bases de datos: una para la aplicación y otra para las pruebas.

   ```sql
   CREATE DATABASE prestamo_equipos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE prestamo_equipos_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

4. Crear las tablas y los datos de prueba:

   ```bash
   php artisan migrate --seed
   ```

5. Iniciar el servidor y abrir <http://127.0.0.1:8000>:

   ```bash
   php artisan serve
   ```

## Usuarios de prueba

Todos usan la contraseña de demostración `password`. Solo sirve para el entorno local.

| Rol | Correo |
|---|---|
| Administrador | `administrador@prestamos.test` |
| Funcionario | `funcionario@prestamos.test` |
| Docente | `docente@prestamos.test` |
| Estudiante | `estudiante@prestamos.test` |

## Pruebas

```bash
php artisan test
```

Las pruebas usan la base `prestamo_equipos_test`, que borran y vuelven a crear en cada ejecución. No tocan la base de la aplicación.

## Autenticación de dos factores

Es opcional para los cuatro roles. Cada usuario la activa en la pestaña **2FA**, con una aplicación autenticadora como Google Authenticator.

## Tecnologías

Laravel 12, Laravel Breeze, Laravel Fortify, Blade, Eloquent, Tailwind CSS y MariaDB.
