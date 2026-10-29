# Desarrollo local

## Versiones

El contenedor usa PHP 8.3, una rama con actualizaciones de seguridad hasta el 31-12-2027 según [PHP.net](https://www.php.net/supported-versions.php). La versión y las extensiones disponibles en InfinityFree aún no se han confirmado; comprobar en el panel que ofrece PHP 8.3 y `pdo_mysql` antes de fijar la compatibilidad de publicación. No se publica desde este entorno.

MySQL 8.4 usa InnoDB como motor predeterminado y se configura explícitamente. PHPUnit y PHP_CodeSniffer se instalan como dependencias de desarrollo mediante Composer y quedan fijados en `composer.lock`.

## Arranque

1. Copiar `.env.example` a `.env` y reemplazar ambos valores de contraseña con cadenas aleatorias distintas. Generar también un secreto JWT aleatorio (al menos 64 caracteres hexadecimales). `.env` está excluido de Git.
2. Ejecutar `docker compose up --build -d`.
3. Aplicar migraciones con `docker compose exec app php bin/migrate.php`; para preparar las migraciones de pruebas aisladas usa `docker compose exec app php bin/migrate.php --test`. Crear el primer administrador con `docker compose exec app php bin/create-admin.php`. El asistente de inicialización no imprime la contraseña.
4. Abrir `http://localhost:8080/`; la interfaz consume la API en `/api/v1`. El endpoint `http://localhost:8080/health.php` comprueba la conexión de PHP a MySQL.
5. Ejecutar la suite de pruebas con `docker compose exec app composer test` y las reglas de estilo con `docker compose exec app composer lint`. Las pruebas de reservas usan `DB_TEST_DATABASE`, no la base de desarrollo; el bootstrap rechaza que ambas sean iguales.
6. Detener los servicios con `docker compose down`. `docker compose down -v` también elimina permanentemente los datos locales de MySQL.

MySQL solo se expone dentro de la red privada de Compose; el sitio se publica en `127.0.0.1:8080`.

### Restablecer la contraseña de un administrador local

Si no recuerdas el correo, este comando muestra las cuentas de administrador en la terminal, permite seleccionar una y solicita la contraseña nueva dos veces sin mostrarla. No modifica el correo ni el estado de la cuenta:

```sh
docker compose exec app php bin/reset-admin-password.php
```

Ejecuta el comando desde una terminal interactiva y escribe la contraseña solo en sus solicitudes locales; no la incluyas como argumento, en el chat ni en archivos del proyecto.

## Cobertura de pruebas (solo desarrollo)

Xdebug está disponible en la imagen local, pero queda desactivado normalmente. Generar cobertura bajo demanda:

```sh
docker compose exec -e XDEBUG_MODE=coverage app vendor/bin/phpunit --coverage-text
```

La cobertura no establece un umbral de aceptación.

## Respaldo y restauración local de MySQL

Ejecutar desde WSL o Git Bash en la raíz del proyecto, para que la redirección conserve el volcado SQL como UTF-8. Los respaldos quedan fuera de Git en `.backups/`.

```sh
mkdir -p .backups
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump --user=root --single-transaction --routines --triggers "$MYSQL_DATABASE"' > ".backups/uyuni-tour-$(date +%Y%m%d-%H%M%S).sql"
```

Para restaurar en una base de recuperación vacía, crea una base nueva y transmite el archivo de respaldo a MySQL. No apuntes el restore a la base de datos que contiene los datos que deseas preservar:

```sh
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --execute="CREATE DATABASE uyuni_tour_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"'
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --database=uyuni_tour_restore' < .backups/uyuni-tour-YYYYMMDD-HHMMSS.sql
```

Sustituye el nombre de ejemplo por el archivo existente. La restauración de ejemplo no se ejecutó; revisa el destino y el respaldo antes de correrla. Nunca uses `docker compose down -v` como método de recuperación.
