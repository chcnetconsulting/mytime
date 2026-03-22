<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Http\Exception\UnauthorizedException;
use Jumbojett\OpenIDConnectClient;

class AuthController extends Controller
{
    public function initialize(): void
    {
        parent::initialize();

        // Load auth component without requiring an existing identity
        // because callback needs to setIdentity().
        $this->loadComponent('Authentication.Authentication', [
            'requireIdentity' => false
        ]);
    }

    /**
     * /login → redirects to Auth0 login page
     */
    public function login()
    {
        $cfg = Configure::read('Oidc');
	$this->log("1");
	$this->log("cfg:".json_encode($cfg));
        $oidc = new OpenIDConnectClient(
            $cfg['issuer'],
            $cfg['clientId'],
            $cfg['clientSecret']
        );

 //       $oidc->setRedirectURL($cfg['redirectUri']);
	$oidc->setRedirectURL("https://mytime.chcnet.at/auth/callback");
	$oidc->addScope($cfg['scopes']);
$this->log("oidc: ".json_encode($oidc));
        // If code is missing, this auto‑redirects to Auth0.
	// On return, it verifies ID Token, nonce, exp, signature.
	//
        $oidc->authenticate(); // may redirect away

        // Authenticated → claims verified
        $claims = $oidc->getVerifiedClaims();

        // Build or find a local user based on Auth0 "sub"
        $users = $this->fetchTable('Users');

        $user = $users->find()
            ->where([
                'oidc_sub' => $claims->sub
            ])
            ->first();
	$this->log("2");
	$this->log(json_encode($user));
        if (!$user) {
            $user = $users->newEntity([
		'oidc_sub'   => $claims->sub,
		'username'   => uniqid(),
                'email'      => $claims->email ?? null,
                'first_name' => $claims->given_name ?? null,
                'last_name'  => $claims->family_name ?? null,
            ]);
	    $users->save($user);
	    $this->log("3");
        }

        // Persist identity in Cake session
        $this->Authentication->setIdentity($user);

        // Optional redirect
	$target = $this->request->getQuery('redirect') ?? '/';
        return $this->redirect($target);
    }

    /**
     * Auth0 callback
     */
    public function callback()
    {
        return $this->login();
    }

    /**
     * Local logout (optional: also call Auth0 end‑session)
     */
    public function logout()
    {
        $this->request->allowMethod(['get', 'post']);
        $this->Authentication->logout();

        return $this->redirect('/auth/login');
    }
}
