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

return [
    // Exceptions
    'unknownAuthenticator'  => '{0} is not a valid authenticator.',
    'unknownUserProvider'   => 'Unable to determine the User Provider to use.',
    'invalidUser'           => 'Unable to locate the specified user.',
    'bannedUser'            => 'Can not log you in as you are currently banned.',
    'logOutBannedUser'      => 'You have been logged out because you have been banned.',
    'badAttempt'            => 'Unable to log you in. Please check your credentials.',
    'noPassword'            => 'Cannot validate a user without a password.',
    'invalidPassword'       => 'Unable to log you in. Please check your password.',
    'noToken'               => 'Every request must have a bearer token in the {0} header.',
    'badToken'              => 'The access token is invalid.',
    'oldToken'              => 'The access token has expired.',
    'noUserEntity'          => 'User Entity must be provided for password validation.',
    'invalidPhone'          => 'Unable to verify the phone address matches the phone on record.',
    'unableSendPhoneToUser' => 'Sorry, there was a problem sending the phone. We could not send an phone to "{0}".',
    'throttled'             => 'Too many requests made from this IP address. You may try again in {0} seconds.',
    'notEnoughPrivilege'    => 'You do not have the necessary permission to perform the desired operation.',
    // JWT Exceptions
    'invalidJWT'     => 'The token is invalid.',
    'expiredJWT'     => 'The token has expired.',
    'beforeValidJWT' => 'The token is not yet available.',

    'phone'           => 'Phone Address',
    'username'        => 'Username',
    'password'        => 'Password',
    'passwordConfirm' => 'Password (again)',
    'haveAccount'     => 'Already have an account?',
    'token'           => 'Token',

    // Buttons
    'confirm' => 'Confirm',
    'send'    => 'Send',

    // Registration
    'register'         => 'Register',
    'registerDisabled' => 'Registration is not currently allowed.',
    'registerSuccess'  => 'Welcome aboard!',

    // Login
    'login'              => 'Login',
    'needAccount'        => 'Need an account?',
    'rememberMe'         => 'Remember me?',
    'forgotPassword'     => 'Forgot your password?',
    'useMagicLink'       => 'Use a Login Link',
    'magicLinkSubject'   => 'Your Login Link',
    'magicTokenNotFound' => 'Unable to verify the link.',
    'magicLinkExpired'   => 'Sorry, link has expired.',
    'checkYourPhone'     => 'Check your phone!',
    'magicLinkDetails'   => 'We just sent you an phone with a Login link inside. It is only valid for {0} minutes.',
    'magicLinkDisabled'  => 'Use of MagicLink is currently not allowed.',
    'successLogout'      => 'You have successfully logged out.',
    'backToLogin'        => 'Back to Login',

    // Passwords
    'errorPasswordLength'       => 'Passwords must be at least {0, number} characters long.',
    'suggestPasswordLength'     => 'Pass phrases - up to 255 characters long - make more secure passwords that are easy to remember.',
    'errorPasswordCommon'       => 'Password must not be a common password.',
    'suggestPasswordCommon'     => 'The password was checked against over 65k commonly used passwords or passwords that have been leaked through hacks.',
    'errorPasswordPersonal'     => 'Passwords cannot contain re-hashed personal information.',
    'suggestPasswordPersonal'   => 'Variations on your phone address or username should not be used for passwords.',
    'errorPasswordTooSimilar'   => 'Password is too similar to the username.',
    'suggestPasswordTooSimilar' => 'Do not use parts of your username in your password.',
    'errorPasswordPwned'        => 'The password {0} has been exposed due to a data breach and has been seen {1, number} times in {2} of compromised passwords.',
    'suggestPasswordPwned'      => '{0} should never be used as a password. If you are using it anywhere change it immediately.',
    'errorPasswordEmpty'        => 'A Password is required.',
    'errorPasswordTooLongBytes' => 'Password cannot exceed {param} bytes in length.',
    'passwordChangeSuccess'     => 'Password changed successfully',
    'userDoesNotExist'          => 'Password was not changed. User does not exist',
    'resetTokenExpired'         => 'Sorry. Your reset token has expired.',

    // Phone Globals
    'phoneInfo'      => 'Some information about the person:',
    'phoneIpAddress' => 'IP Address:',
    'phoneDevice'    => 'Device:',
    'phoneDate'      => 'Date:',

    // 2FA
    'phone2FATitle'       => 'Two Factor Authentication',
    'confirmPhoneAddress' => 'Confirm your phone address.',
    'phoneEnterCode'      => 'Confirm your Phone',
    'phoneConfirmCode'    => 'Enter the 6-digit code we just sent to your phone address.',
    'phone2FASubject'     => 'Your authentication code',
    'phone2FAPhoneBody'    => 'Your authentication code is:',
    'invalid2FAToken'     => 'The code was incorrect.',
    'need2FA'             => 'You must complete a two-factor verification.',
    'needVerification'    => 'Check your phone to complete account activation.',

    // Activate
    'phoneActivateTitle'    => 'Phone Activation',
    'phoneActivateBody'     => 'We just sent an phone to you with a code to confirm your phone address. Copy that code and paste it below.',
    'phoneActivateSubject'  => 'Your activation code',
    'phoneActivatePhoneBody' => 'Please use the code below to activate your account and start using the site.',
    'invalidActivateToken'  => 'The code was incorrect.',
    'needActivate'          => 'You must complete your registration by confirming the code sent to your phone address.',
    'activationBlocked'     => 'You must activate your account before logging in.',

    // Groups
    'unknownGroup' => '{0} is not a valid group.',
    'missingTitle' => 'Groups must have a title.',

    // Permissions
    'unknownPermission' => '{0} is not a valid permission.',
];
