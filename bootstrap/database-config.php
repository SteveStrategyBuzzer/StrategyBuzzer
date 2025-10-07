<?php
// Force PostgreSQL environment variables before any Laravel configuration loads
if (!empty(getenv('PGHOST'))) {
    $_ENV['DB_CONNECTION'] = 'pgsql';
    $_ENV['DB_HOST'] = getenv('PGHOST');
    $_ENV['DB_PORT'] = getenv('PGPORT');
    $_ENV['DB_DATABASE'] = getenv('PGDATABASE');
    $_ENV['DB_USERNAME'] = getenv('PGUSER');
    $_ENV['DB_PASSWORD'] = getenv('PGPASSWORD');
    
    putenv('DB_CONNECTION=pgsql');
    putenv('DB_HOST=' . getenv('PGHOST'));
    putenv('DB_PORT=' . getenv('PGPORT'));
    putenv('DB_DATABASE=' . getenv('PGDATABASE'));
    putenv('DB_USERNAME=' . getenv('PGUSER'));
    putenv('DB_PASSWORD=' . getenv('PGPASSWORD'));
}
