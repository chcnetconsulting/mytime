<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;

/**
 * Auth Controller
 */
class AuthController extends AppController
{
    public function login()
    {
        $config = $this->oidcConfig();
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        $this->request->getSession()->write('Oidc.state', $state);
        $this->request->getSession()->write('Oidc.nonce', $nonce);

        $query = http_build_query([
            'client_id' => $config['clientId'],
            'response_type' => 'code',
            'redirect_uri' => $config['redirectUri'],
            'response_mode' => 'query',
            'scope' => 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
        ]);

        return $this->redirect($config['authorizeUrl'] . '?' . $query);
    }

    public function callback()
    {
        $session = $this->request->getSession();
        $state = (string)$this->request->getQuery('state', '');
        $code = (string)$this->request->getQuery('code', '');

        if ($this->request->getQuery('error')) {
            throw new BadRequestException((string)$this->request->getQuery('error_description', 'Login failed.'));
        }

        if ($state === '' || $state !== $session->read('Oidc.state')) {
            throw new BadRequestException('Invalid login state.');
        }
        if ($code === '') {
            throw new BadRequestException('Missing authorization code.');
        }

        $config = $this->oidcConfig();
        $token = $this->exchangeCode($config, $code);
        $idToken = (string)($token['id_token'] ?? '');
        $this->verifyIdTokenSignature($idToken, $config);
        $claims = $this->decodeIdToken($idToken);
        $this->validateClaims($claims, $config, (string)$session->read('Oidc.nonce'));

        $user = $this->findOrCreateUser($claims);
        $session->delete('Oidc');
        $session->write('Auth.User', $user->toArray());

        $this->Flash->success(__('You are logged in.'));

        return $this->redirect(['controller' => 'Home', 'action' => 'index']);
    }

    public function logout()
    {
        $this->request->getSession()->delete('Auth.User');
        $this->Flash->success(__('You are logged out.'));

        return $this->redirect($this->oidcConfig()['logoutUrl']);
    }

    /**
     * @return array<string, string>
     */
    private function oidcConfig(): array
    {
        $config = (array)Configure::read('Oidc');
        foreach (['tenantId', 'clientId', 'clientSecret', 'redirectUri'] as $key) {
            if (empty($config[$key])) {
                throw new InternalErrorException("Missing OIDC configuration: {$key}");
            }
        }

        $tenant = $config['tenantId'];

        return [
            'tenantId' => $tenant,
            'clientId' => $config['clientId'],
            'clientSecret' => $config['clientSecret'],
            'redirectUri' => $config['redirectUri'],
            'issuer' => "https://login.microsoftonline.com/{$tenant}/v2.0",
            'authorizeUrl' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize",
            'tokenUrl' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            'keysUrl' => "https://login.microsoftonline.com/{$tenant}/discovery/v2.0/keys",
            'logoutUrl' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/logout",
        ];
    }

    /**
     * @param array<string, string> $config
     * @return array<string, mixed>
     */
    protected function exchangeCode(array $config, string $code): array
    {
        $http = new Client();
        $response = $http->post($config['tokenUrl'], [
            'client_id' => $config['clientId'],
            'client_secret' => $config['clientSecret'],
            'code' => $code,
            'redirect_uri' => $config['redirectUri'],
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->isOk()) {
            $body = (array)$response->getJson();
            $description = (string)($body['error_description'] ?? $body['error'] ?? 'Token exchange failed.');

            throw new BadRequestException('Token exchange failed: ' . $description);
        }

        return (array)$response->getJson();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeIdToken(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new BadRequestException('Invalid ID token.');
        }

        $claims = json_decode($this->base64UrlDecode($parts[1]), true);
        if (!is_array($claims)) {
            throw new BadRequestException('Invalid ID token claims.');
        }

        return $claims;
    }

    /**
     * @param array<string, string> $config
     */
    private function verifyIdTokenSignature(string $idToken, array $config): void
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new BadRequestException('Invalid ID token.');
        }

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'RS256' || empty($header['kid'])) {
            throw new BadRequestException('Invalid ID token header.');
        }

        $key = $this->findSigningKey($config['keysUrl'], (string)$header['kid']);
        $publicKey = openssl_pkey_get_public($this->jwkToPem($key));
        if (!$publicKey) {
            throw new BadRequestException('Invalid token signing key.');
        }

        $valid = openssl_verify(
            $parts[0] . '.' . $parts[1],
            $this->base64UrlDecode($parts[2]),
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        if ($valid !== 1) {
            throw new BadRequestException('Invalid token signature.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function findSigningKey(string $keysUrl, string $kid): array
    {
        $response = (new Client())->get($keysUrl);
        if (!$response->isOk()) {
            throw new BadRequestException('Could not load token signing keys.');
        }

        $keys = (array)($response->getJson()['keys'] ?? []);
        foreach ($keys as $key) {
            if (is_array($key) && ($key['kid'] ?? null) === $kid) {
                return $key;
            }
        }

        throw new BadRequestException('Token signing key not found.');
    }

    /**
     * @param array<string, string> $key
     */
    private function jwkToPem(array $key): string
    {
        $rsaPublicKey = $this->asn1Sequence(
            $this->asn1Integer($this->base64UrlDecode($key['n']))
            . $this->asn1Integer($this->base64UrlDecode($key['e']))
        );
        $algorithm = $this->asn1Sequence("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");
        $subjectPublicKeyInfo = $this->asn1Sequence(
            $algorithm . "\x03" . $this->asn1Length(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private function asn1Integer(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '' || (ord($value[0]) & 0x80)) {
            $value = "\x00" . $value;
        }

        return "\x02" . $this->asn1Length(strlen($value)) . $value;
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30" . $this->asn1Length(strlen($value)) . $value;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /**
     * @param array<string, mixed> $claims
     * @param array<string, string> $config
     */
    private function validateClaims(array $claims, array $config, string $nonce): void
    {
        if (($claims['iss'] ?? null) !== $config['issuer']) {
            throw new BadRequestException('Invalid token issuer.');
        }
        if (($claims['aud'] ?? null) !== $config['clientId']) {
            throw new BadRequestException('Invalid token audience.');
        }
        if (($claims['nonce'] ?? null) !== $nonce) {
            throw new BadRequestException('Invalid token nonce.');
        }
        if (($claims['exp'] ?? 0) < time()) {
            throw new BadRequestException('Expired token.');
        }
    }

    /**
     * @param array<string, mixed> $claims
     * @return \Cake\Datasource\EntityInterface
     */
    private function findOrCreateUser(array $claims)
    {
        $email = (string)($claims['email'] ?? $claims['preferred_username'] ?? '');
        if ($email === '') {
            throw new BadRequestException('Missing user email.');
        }

        $users = $this->fetchTable('Users');
        $user = $users->find()->where(['email' => $email])->first();
        if ($user) {
            $ownerEmail = (string)Configure::read('Auth.ownerEmail', '');
            if ($ownerEmail !== '' && strcasecmp($email, $ownerEmail) === 0 && !$user->is_admin) {
                $user->is_admin = true;
                $users->saveOrFail($user);
            }

            return $user;
        }

        $groups = $this->fetchTable('Groups');
        $group = $groups->find()->where(['name' => 'Default'])->first();
        if (!$group) {
            $group = $groups->newEntity(['name' => 'Default']);
            $group = $groups->saveOrFail($group);
        }

        $ownerEmail = (string)Configure::read('Auth.ownerEmail', '');
        $name = trim((string)($claims['name'] ?? ''));
        $parts = preg_split('/\s+/', $name, 2) ?: [];
        $user = $users->newEntity([
            'username' => (string)($claims['preferred_username'] ?? $email),
            'email' => $email,
            'group_id' => $group->id,
            'is_admin' => $ownerEmail !== '' && strcasecmp($email, $ownerEmail) === 0,
            'first_name' => $parts[0] ?? null,
            'last_name' => $parts[1] ?? null,
        ]);

        return $users->saveOrFail($user);
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = strtr($value, '-_', '+/');
        $padding = (4 - strlen($decoded) % 4) % 4;
        $padded = str_pad($decoded, strlen($decoded) + $padding, '=', STR_PAD_RIGHT);

        return (string)base64_decode($padded);
    }
}
