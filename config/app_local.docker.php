<?php
declare(strict_types=1);

return [
    'debug' => filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN),

    'Security' => [
        'salt' => env('SECURITY_SALT', ''),
    ],

    'Datasources' => [
        // Ziel ist der gemeinsame PostgreSQL-Cluster pgsql92 im Namespace
        // `default` des MicroK8s-Clusters (Zalando-Operator, PostgreSQL 17).
        // Werte kommen aus der ConfigMap mytime-config, das Passwort aus dem
        // Secret mytime-db — siehe k8s/README.md.
        'default' => [
            'host' => env('DB_HOST', 'pgsql92.default.svc.cluster.local'),
            'port' => (int)env('DB_PORT', '5432'),
            'username' => env('DB_USERNAME', 'mytime'),
            'password' => env('DB_PASSWORD', ''),
            'database' => env('DB_DATABASE', 'mytime'),
            'schema' => env('DB_SCHEMA', 'public'),
            // 'utf8', nicht 'utf8mb4' — s. Begruendung in config/app.php.
            'encoding' => 'utf8',
            // Spilo/Zalando setzt in pg_hba.conf "hostnossl … reject" und
            // "hostssl … md5": ohne Verschluesselung kommt keine Verbindung
            // zustande. Bewusst 'require' und nicht 'verify-*' — das
            // Serverzertifikat ist selbstsigniert und wird bei jedem Start
            // neu erzeugt, eine Pruefung waere eine Zeitbombe.
            'ssl' => filter_var(env('DB_SSL', true), FILTER_VALIDATE_BOOLEAN),
            'ssl_mode' => env('DB_SSLMODE', 'require'),
            'quoteIdentifiers' => filter_var(env('DATABASE_QUOTE_IDENTIFIERS', true), FILTER_VALIDATE_BOOLEAN),
            'url' => env('DATABASE_URL', null) ?: null,
        ],
        'test' => [
            'host' => env('DATABASE_TEST_HOST', 'localhost'),
            'port' => (int)env('DATABASE_TEST_PORT', '5432'),
            'username' => env('DATABASE_TEST_USER', 'mytime'),
            'password' => env('DATABASE_TEST_PASS', ''),
            'database' => env('DATABASE_TEST_NAME', 'mytimetests'),
            'encoding' => env('DATABASE_TEST_ENCODING', 'utf8'),
            'quoteIdentifiers' => filter_var(env('DATABASE_TEST_QUOTE_IDENTIFIERS', true), FILTER_VALIDATE_BOOLEAN),
            'url' => env('DATABASE_TEST_URL', env('DATABASE_TEST_HOST') ? null : 'sqlite://127.0.0.1/tmp/tests.sqlite'),
        ],
    ],

    'EmailTransport' => [
        'default' => [
            'host' => env('EMAIL_HOST', 'localhost'),
            'port' => (int)env('EMAIL_PORT', 25),
            'username' => env('EMAIL_USERNAME', null),
            'password' => env('EMAIL_PASSWORD', null),
            'client' => null,
            'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
        ],
    ],

    'Auth' => [
        'disabled' => filter_var(env('MYTIME_AUTH_DISABLED', false), FILTER_VALIDATE_BOOLEAN),
        'ownerEmail' => env('OIDC_OWNER_EMAIL', env('ENTRA_OWNER_EMAIL', null)),
    ],

    'Oidc' => [
        'provider' => env('OIDC_PROVIDER', 'entra'),
        'providers' => [
            'entra' => [
                'label' => 'Microsoft Entra ID',
                'tenantId' => env('ENTRA_TENANT_ID', null),
                'clientId' => env('ENTRA_CLIENT_ID', null),
                'clientSecret' => env('ENTRA_CLIENT_SECRET', null),
                'redirectUri' => env('ENTRA_REDIRECT_URI', 'http://localhost:8765/auth/callback'),
                'scope' => env('ENTRA_SCOPE', 'openid profile email'),
            ],
            'keycloak' => [
                'label' => 'Keycloak',
                'issuer' => env('KEYCLOAK_ISSUER', null),
                'clientId' => env('KEYCLOAK_CLIENT_ID', null),
                'clientSecret' => env('KEYCLOAK_CLIENT_SECRET', null),
                'redirectUri' => env('KEYCLOAK_REDIRECT_URI', env('OIDC_REDIRECT_URI', 'http://localhost:8765/auth/callback')),
                'scope' => env('KEYCLOAK_SCOPE', 'openid profile email'),
                'logoutUrl' => env('KEYCLOAK_LOGOUT_URL', null),
            ],
            'google' => [
                'label' => 'Google',
                'issuer' => 'https://accounts.google.com',
                'clientId' => env('GOOGLE_CLIENT_ID', null),
                'clientSecret' => env('GOOGLE_CLIENT_SECRET', null),
                'redirectUri' => env('GOOGLE_REDIRECT_URI', env('OIDC_REDIRECT_URI', 'http://localhost:8765/auth/callback')),
                'scope' => env('GOOGLE_SCOPE', 'openid profile email'),
                'authorizeUrl' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'tokenUrl' => 'https://oauth2.googleapis.com/token',
                'keysUrl' => 'https://www.googleapis.com/oauth2/v3/certs',
                'logoutUrl' => env('GOOGLE_LOGOUT_URL', null),
            ],
            'custom' => [
                'label' => env('OIDC_LABEL', 'OpenID Connect'),
                'issuer' => env('OIDC_ISSUER', null),
                'clientId' => env('OIDC_CLIENT_ID', null),
                'clientSecret' => env('OIDC_CLIENT_SECRET', null),
                'redirectUri' => env('OIDC_REDIRECT_URI', 'http://localhost:8765/auth/callback'),
                'scope' => env('OIDC_SCOPE', 'openid profile email'),
                'authorizeUrl' => env('OIDC_AUTHORIZE_URL', null),
                'tokenUrl' => env('OIDC_TOKEN_URL', null),
                'keysUrl' => env('OIDC_KEYS_URL', null),
                'logoutUrl' => env('OIDC_LOGOUT_URL', null),
            ],
        ],
    ],
];
