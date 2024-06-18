<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace CodeIgniter\Shield\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Models\LoginModel;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Traits\Viewable;

/**
 * Handles "Magic Link" logins - an phone-based
 * no-password login protocol. This works much
 * like password reset would, but Shield provides
 * this in place of password reset. It can also
 * be used on it's own without an phone/password
 * login strategy.
 */
class MagicLinkController extends BaseController
{
    use Viewable;

    /**
     * @var UserModel
     */
    protected $provider;

    public function __construct()
    {
        /** @var class-string<UserModel> $providerClass */
        $providerClass = setting('Auth.userProvider');

        $this->provider = new $providerClass();
    }

    /**
     * Displays the view to enter their phone address
     * so an phone can be sent to them.
     *
     * @return RedirectResponse|string
     */
    public function loginView()
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->route('login')->with('error', lang('Auth.magicLinkDisabled'));
        }

        if (auth()->loggedIn()) {
            return redirect()->to(config('Auth')->loginRedirect());
        }

        return $this->view(setting('Auth.views')['magic-link-login']);
    }

    /**
     * Receives the phone from the user, creates the hash
     * to a user identity, and sends an phone to the given
     * phone address.
     *
     * @return RedirectResponse|string
     */
    public function loginAction()
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->route('login')->with('error', lang('Auth.magicLinkDisabled'));
        }

        // Validate phone format
        $rules = $this->getValidationRules();
        if (! $this->validateData($this->request->getPost(), $rules, [], config('Auth')->DBGroup)) {
            return redirect()->route('magic-link')->with('errors', $this->validator->getErrors());
        }

        // Check if the user exists
        $phone = $this->request->getPost('phone');
        $user  = $this->provider->findByCredentials(['phone' => $phone]);

        if ($user === null) {
            return redirect()->route('magic-link')->with('error', lang('Auth.invalidPhone'));
        }

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        // Delete any previous magic-link identities
        $identityModel->deleteIdentitiesByType($user, Session::ID_TYPE_MAGIC_LINK);

        // Generate the code and save it as an identity
        helper('text');
        $token = random_string('crypto', 20);

        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
            'secret'  => $token,
            'expires' => Time::now()->addSeconds(setting('Auth.magicLinkLifetime')),
        ]);

        /** @var IncomingRequest $request */
        $request = service('request');

        $ipAddress = $request->getIPAddress();
        $userAgent = (string) $request->getUserAgent();
        $date      = Time::now()->toDateTimeString();

        // Send the user an phone with the code
		$client = \Config\Services::curlrequest([
			'baseURI' => setting('Auth.smsBaseUrl')
		]);
		
		$response = $client->post('verify', [
			'verify' => false,
			'headers' => [
				'Accept'    => 'application/json',
				'X-API-KEY' => setting('Auth.smsSecretToken')
			],
			'json' => [
				'TemplateId' => 100000,
				'Mobile' => $user->phone,
				'Parameters' => [
					[
						'Name' => 'Code',
						'Value' => $token
					]
				]
			]
		]);
		
		if (200 !== $response->getStatusCode()) {

			// log_message('error', $phone->printDebugger(['headers']));
            return redirect()->route('magic-link')->with('error', lang('Auth.unableSendPhoneToUser', [$user->phone]));
        }

        return $this->displayMessage();
    }

    /**
     * Display the "What's happening/next" message to the user.
     */
    protected function displayMessage(): string
    {
        return $this->view(setting('Auth.views')['magic-link-message']);
    }

    /**
     * Handles the GET request from the phone
     */
    public function verify(): RedirectResponse
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->route('login')->with('error', lang('Auth.magicLinkDisabled'));
        }

        $token = $this->request->getGet('token');

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        $identity = $identityModel->getIdentityBySecret(Session::ID_TYPE_MAGIC_LINK, $token);

        $identifier = $token ?? '';

        // No token found?
        if ($identity === null) {
            $this->recordLoginAttempt($identifier, false);

            $credentials = ['magicLinkToken' => $token];
            Events::trigger('failedLogin', $credentials);

            return redirect()->route('magic-link')->with('error', lang('Auth.magicTokenNotFound'));
        }

        // Delete the db entry so it cannot be used again.
        $identityModel->delete($identity->id);

        // Token expired?
        if (Time::now()->isAfter($identity->expires)) {
            $this->recordLoginAttempt($identifier, false);

            $credentials = ['magicLinkToken' => $token];
            Events::trigger('failedLogin', $credentials);

            return redirect()->route('magic-link')->with('error', lang('Auth.magicLinkExpired'));
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        // If an action has been defined
        if ($authenticator->hasAction($identity->user_id)) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.needActivate'));
        }

        // Log the user in
        $authenticator->loginById($identity->user_id);

        $user = $authenticator->getUser();

        $this->recordLoginAttempt($identifier, true, $user->id);

        // Give the developer a way to know the user
        // logged in via a magic link.
        session()->setTempdata('magicLogin', true);

        Events::trigger('magicLogin');

        // Get our login redirect url
        return redirect()->to(config('Auth')->loginRedirect());
    }

    /**
     * @param int|string|null $userId
     */
    private function recordLoginAttempt(
        string $identifier,
        bool $success,
        $userId = null
    ): void {
        /** @var LoginModel $loginModel */
        $loginModel = model(LoginModel::class);

        $loginModel->recordLoginAttempt(
            Session::ID_TYPE_MAGIC_LINK,
            $identifier,
            $success,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            $userId
        );
    }

    /**
     * Returns the rules that should be used for validation.
     *
     * @return array<string, array<string, list<string>|string>>
     */
    protected function getValidationRules(): array
    {
        return [
            'phone' => config('Auth')->phoneValidationRules,
        ];
    }
}
