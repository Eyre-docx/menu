# SoulBound POS

PHP/MySQL point-of-sale system for guest ordering and staff operations.

## Run locally in VS Code

1. Install PHP 8.1 or newer, MySQL 8, and the VS Code PHP extensions.
2. Create a MySQL database named `pos_db`.
3. Import [`schema.sql`](./schema.sql).
4. Confirm the local connection values in [`db.php`](./db.php), or update them for your local MySQL installation.
5. From this folder, start PHP's development server:

   ```powershell
   php -S localhost:8000
   ```

6. Open <http://localhost:8000> in a browser.

The application uses PHP sessions and MySQL, so it cannot run on GitHub Pages. GitHub is used here for source control and collaboration; production hosting must provide PHP and MySQL (or a compatible managed database).

## Repository contents

- `index.php` - entry point
- `menu.php` and `cart.php` - guest ordering flow
- `admin_*.php` - staff/admin screens
- `db.php` - database and session helper
- `schema.sql` - database schema and seed data
- `assets/menu/` - menu images

## Security notes

Do not commit passwords, session cookies, generated HTTP responses, or production database credentials. Configure production secrets through the hosting provider's environment variables and replace the development database credentials in `db.php` with environment-based configuration before deploying.
