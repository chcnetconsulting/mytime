<?php
/*
 * Local configuration file to provide any overrides to your app.php configuration.
 * Copy and save this file as app_local.php and make changes as required.
 * Note: It is not recommended to commit files with credentials such as app_local.php
 * into source code version control.
 */
return [
    /*
     * Debug Level:
     *
     * Production Mode:
     * false: No error messages, errors, or warnings shown.
     *
     * Development Mode:
     * true: Errors and warnings shown.
     */
    'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN),

    /*
     * Security and encryption configuration
     *
     * - salt - A random string used in security hashing methods.
     *   The salt value is also used as the encryption key.
     *   You should treat it as extremely sensitive data.
     */
    'Security' => [
        'salt' => env('SECURITY_SALT', '__SALT__'),
    ],

    /*
     * Connection information used by the ORM to connect
     * to your application's datastores.
     *
     * See app.php for more configuration options.
     */
    'Datasources' => [
        'default' => [
            'host' => env('DB_HOST', 'localhost'),
            'port' => (int)env('DB_PORT', '5432'),
            'username' => env('DB_USERNAME', 'mytime'),
            'password' => env('DB_PASSWORD', ''),
            'database' => env('DB_DATABASE', 'mytime'),
            /*
             * Anderes Schema als 'public'? Hier eintragen.
             */
            'schema' => env('DB_SCHEMA', 'public'),
            'encoding' => 'utf8',
            /*
             * Im Cluster Pflicht (Spilo lehnt unverschluesselte Verbindungen
             * ab), lokal meist unnoetig.
             */
            'ssl' => filter_var(env('DB_SSL', false), FILTER_VALIDATE_BOOLEAN),
            'ssl_mode' => env('DB_SSLMODE', 'prefer'),
            /*
             * Alternativ die gesamte Verbindung als DSN:
             *   postgres://benutzer:passwort@host:5432/datenbank
             */
            'url' => env('DATABASE_URL', null),
        ],

        /*
         * The test connection is used during the test suite.
         * Ohne gesetzte Umgebung SQLite, sonst PostgreSQL — s. docs/testing.md.
         */
        'test' => [
            'host' => env('DATABASE_TEST_HOST', 'localhost'),
            'port' => (int)env('DATABASE_TEST_PORT', '5432'),
            'username' => env('DATABASE_TEST_USER', 'mytime'),
            'password' => env('DATABASE_TEST_PASS', ''),
            'database' => env('DATABASE_TEST_NAME', 'mytime_test'),
            'schema' => env('DB_SCHEMA', 'public'),
            'url' => env('DATABASE_TEST_URL', 'sqlite://127.0.0.1/tmp/tests.sqlite'),
        ],
    ],

    /*
     * Email configuration.
     *
     * Host and credential configuration in case you are using SmtpTransport
     *
     * See app.php for more configuration options.
     */
    'EmailTransport' => [
        'default' => [
            'host' => 'localhost',
            'port' => 25,
            'username' => null,
            'password' => null,
            'client' => null,
            'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
        ],
    ],
];
