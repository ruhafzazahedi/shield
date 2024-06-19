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

namespace CodeIgniter\Shield\Authentication\Actions;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Entities\UserIdentity;
use CodeIgniter\Shield\Exceptions\RuntimeException;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Traits\Viewable;

/**
 * Class Phone2FA
 *
 * Sends an phone to the user with a code to verify their account.
 */
class Phone2FA implements ActionInterface
{
    use Viewable;

    private string $type = Session::ID_TYPE_PHONE_2FA;

    /**
     * Displays the "Hey we're going to send you a number to your phone"
     * message to the user with a prompt to continue.
     */
    public function show(): string
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $user = $authenticator->getPendingUser();
        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        $this->createIdentity($user);

        return $this->view(config('Auth')->views['action_phone_2fa'], ['user' => $user]);
    }

    /**
     * Generates the random number, saves it as a temp identity
     * with the user, and fires off an phone to the user with the code,
     * then displays the form to accept the 6 digits
     *
     * @return RedirectResponse|string
     */
    public function handle(IncomingRequest $request)
    {
        $phone = $request->getPost('phone');

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $user = $authenticator->getPendingUser();
        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        if (empty($phone) || $phone !== $user->phone) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.invalidPhone'));
        }

        $identity = $this->getIdentity($user);

        if (! $identity instanceof UserIdentity) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.need2FA'));
        }

        $ipAddress = $request->getIPAddress();
        $userAgent = (string) $request->getUserAgent();
        $date      = Time::now()->toDateTimeString();

        // Send the user an phone with the code
		$client = \Config\Services::curlrequest([
			'baseURI' => config('Auth')->smsBaseUrl
		]);
		
		$response = $client->post('verify', [
			'verify' => false,
			'headers' => [
				'Accept'    => 'application/json',
				'X-API-KEY' => config('Auth')->smsSecretToken
			],
			'json' => [
				'TemplateId' => 100000,
				'Mobile' => $user->phone,
				'Parameters' => [
					[
						'Name' => 'Code',
						'Value' => $identity->secret
					]
				]
			]
		]);
		
		if (200 !== $response->getStatusCode()) {

			// log_message('error', $phone->printDebugger(['headers']));
            return redirect()->route('magic-link')->with('error', lang('Auth.unableSendPhoneToUser', [$user->phone]));
        }

        return $this->view(config('Auth')->views['action_phone_2fa_verify']);
    }

    /**
     * Attempts to verify the code the user entered.
     *
     * @return RedirectResponse|string
     */
    public function verify(IncomingRequest $request)
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $postedToken = $request->getPost('token');

        $user = $authenticator->getPendingUser();
        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        $identity = $this->getIdentity($user);

        // Token mismatch? Let them try again...
        if (! $authenticator->checkAction($identity, $postedToken)) {
            session()->setFlashdata('error', lang('Auth.invalid2FAToken'));

            return $this->view(config('Auth')->views['action_phone_2fa_verify']);
        }

        // Get our login redirect url
        return redirect()->to(config('Auth')->loginRedirect());
    }

    /**
     * Creates an identity for the action of the user.
     *
     * @return string secret
     */
    public function createIdentity(User $user): string
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        // Delete any previous identities for action
        $identityModel->deleteIdentitiesByType($user, $this->type);

        $generator = static fn (): string => random_string('nozero', 6);

        return $identityModel->createCodeIdentity(
            $user,
            [
                'type'  => $this->type,
                'name'  => 'login',
                'extra' => lang('Auth.need2FA'),
            ],
            $generator
        );
    }

    /**
     * Returns an identity for the action of the user.
     */
    private function getIdentity(User $user): ?UserIdentity
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        return $identityModel->getIdentityByType(
            $user,
            $this->type
        );
    }

    /**
     * Returns the string type of the action class.
     */
    public function getType(): string
    {
        return $this->type;
    }
}
