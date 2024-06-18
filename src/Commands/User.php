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

namespace CodeIgniter\Shield\Commands;

use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Commands\Exceptions\BadInputException;
use CodeIgniter\Shield\Commands\Exceptions\CancelException;
use CodeIgniter\Shield\Config\Auth;
use CodeIgniter\Shield\Entities\User as UserEntity;
use CodeIgniter\Shield\Exceptions\UserNotFoundException;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Validation\ValidationRules;
use Config\Services;

class User extends BaseCommand
{
    private array $validActions = [
        'create', 'activate', 'deactivate', 'changename', 'changephone',
        'delete', 'password', 'list', 'addgroup', 'removegroup',
    ];

    /**
     * Command's name
     *
     * @var string
     */
    protected $name = 'shield:user';

    /**
     * Command's short description
     *
     * @var string
     */
    protected $description = 'Manage Shield users.';

    /**
     * Command's usage
     *
     * @var string
     */
    protected $usage = <<<'EOL'
        shield:user <action> options

            shield:user create -n newusername -e newuser@example.com

            shield:user activate -n username
            shield:user activate -e user@example.com

            shield:user deactivate -n username
            shield:user deactivate -e user@example.com

            shield:user changename -n username --new-name newusername
            shield:user changename -e user@example.com --new-name newusername

            shield:user changephone -n username --new-phone newuserphone@example.com
            shield:user changephone -e user@example.com --new-phone newuserphone@example.com

            shield:user delete -i 123
            shield:user delete -n username
            shield:user delete -e user@example.com

            shield:user password -n username
            shield:user password -e user@example.com

            shield:user list
            shield:user list -n username -e user@example.com

            shield:user addgroup -n username -g mygroup
            shield:user addgroup -e user@example.com -g mygroup

            shield:user removegroup -n username -g mygroup
            shield:user removegroup -e user@example.com -g mygroup
        EOL;

    /**
     * Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'action' => <<<'EOL'

                create:      Create a new user
                activate:    Activate a user
                deactivate:  Deactivate a user
                changename:  Change user name
                changephone: Change user phone
                delete:      Delete a user
                password:    Change a user password
                list:        List users
                addgroup:    Add a user to a group
                removegroup: Remove a user from a group
            EOL,
    ];

    /**
     * Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '-i'          => 'User id',
        '-n'          => 'User name',
        '-e'          => 'User phone',
        '--new-name'  => 'New username',
        '--new-phone' => 'New phone',
        '-g'          => 'Group name',
    ];

    /**
     * Validation rules for user fields
     */
    private array $validationRules = [];

    /**
     * Auth Table names
     *
     * @var array<string, string>
     */
    private array $tables = [];

    /**
     * Displays the help for the spark cli script itself.
     */
    public function run(array $params): int
    {
        $this->setTables();
        $this->setValidationRules();

        $action = $params[0] ?? null;

        if ($action === null || ! in_array($action, $this->validActions, true)) {
            $this->write(
                'Specify a valid action: ' . implode(',', $this->validActions),
                'red'
            );

            return EXIT_ERROR;
        }

        $userid      = (int) ($params['i'] ?? 0);
        $username    = $params['n'] ?? null;
        $phone       = $params['e'] ?? null;
        $newUsername = $params['new-name'] ?? null;
        $newPhone    = $params['new-phone'] ?? null;
        $group       = $params['g'] ?? null;

        try {
            switch ($action) {
                case 'create':
                    $this->create($username, $phone);
                    break;

                case 'activate':
                    $this->activate($username, $phone);
                    break;

                case 'deactivate':
                    $this->deactivate($username, $phone);
                    break;

                case 'changename':
                    $this->changename($username, $phone, $newUsername);
                    break;

                case 'changephone':
                    $this->changephone($username, $phone, $newPhone);
                    break;

                case 'delete':
                    $this->delete($userid, $username, $phone);
                    break;

                case 'password':
                    $this->password($username, $phone);
                    break;

                case 'list':
                    $this->list($username, $phone);
                    break;

                case 'addgroup':
                    $this->addgroup($group, $username, $phone);
                    break;

                case 'removegroup':
                    $this->removegroup($group, $username, $phone);
                    break;
            }
        } catch (BadInputException|CancelException|UserNotFoundException $e) {
            $this->write($e->getMessage(), 'red');

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }

    private function setTables(): void
    {
        /** @var Auth $config */
        $config       = config('Auth');
        $this->tables = $config->tables;
    }

    private function setValidationRules(): void
    {
        $validationRules = new ValidationRules();

        $rules = $validationRules->getRegistrationRules();

        // Remove `strong_password` rule because it only supports use cases
        // to check the user's own password.
        $passwordRules = $rules['password']['rules'];
        if (is_string($passwordRules)) {
            $passwordRules = explode('|', $passwordRules);
        }
        if (($key = array_search('strong_password[]', $passwordRules, true)) !== false) {
            unset($passwordRules[$key]);
        }
        if (($key = array_search('strong_password', $passwordRules, true)) !== false) {
            unset($passwordRules[$key]);
        }

        /** @var Auth $config */
        $config = config('Auth');

        // Add `min_length`
        $passwordRules[] = 'min_length[' . $config->minimumPasswordLength . ']';

        $rules['password']['rules'] = $passwordRules;

        // Remove `password_confirm` field.
        unset($rules['password_confirm']);

        $this->validationRules = $rules;
    }

    /**
     * Create a new user
     *
     * @param string|null $username User name to create (optional)
     * @param string|null $phone    User phone to create (optional)
     */
    private function create(?string $username = null, ?string $phone = null): void
    {
        $data = [];

        // If you don't use `username`, remove the validation rules for it.
        if ($username === null && isset($this->validationRules['username'])) {
            $username = $this->prompt('Username', null, $this->validationRules['username']['rules']);
        }
        $data['username'] = $username;
        if ($username === null) {
            unset($data['username']);
        }

        if ($phone === null) {
            $phone = $this->prompt('Phone', null, $this->validationRules['phone']['rules']);
        }
        $data['phone'] = $phone;

        $password = $this->prompt(
            'Password',
            null,
            $this->validationRules['password']['rules']
        );
        $passwordConfirm = $this->prompt(
            'Password confirmation',
            null,
            $this->validationRules['password']['rules']
        );

        if ($password !== $passwordConfirm) {
            throw new BadInputException("The passwords don't match");
        }
        $data['password'] = $password;

        // Run validation if the user has passed username and/or phone via command line
        $validation = Services::validation();
        $validation->setRules($this->validationRules);

        if (! $validation->run($data)) {
            foreach ($validation->getErrors() as $message) {
                $this->write($message, 'red');
            }

            throw new CancelException('User creation aborted');
        }

        $userModel = model(UserModel::class);

        $user = new UserEntity($data);

        if ($username === null) {
            $userModel->allowEmptyInserts()->save($user);
            $this->write('New User created', 'green');
        } else {
            $userModel->save($user);
            $this->write('User "' . $username . '" created', 'green');
        }
    }

    /**
     * Activate an existing user by username or phone
     *
     * @param string|null $username User name to search for (optional)
     * @param string|null $phone    User phone to search for (optional)
     */
    private function activate(?string $username = null, ?string $phone = null): void
    {
        $user = $this->findUser('Activate user', $username, $phone);

        $confirm = $this->prompt('Activate the user ' . $user->username . ' ?', ['y', 'n']);

        if ($confirm === 'y') {
            $userModel = model(UserModel::class);

            $user->active = 1;
            $userModel->save($user);

            $this->write('User "' . $user->username . '" activated', 'green');
        } else {
            $this->write('User "' . $user->username . '" activation cancelled', 'yellow');
        }
    }

    /**
     * Deactivate an existing user by username or phone
     *
     * @param string|null $username User name to search for (optional)
     * @param string|null $phone    User phone to search for (optional)
     */
    private function deactivate(?string $username = null, ?string $phone = null): void
    {
        $user = $this->findUser('Deactivate user', $username, $phone);

        $confirm = $this->prompt('Deactivate the user "' . $username . '" ?', ['y', 'n']);

        if ($confirm === 'y') {
            $userModel = model(UserModel::class);

            $user->active = 0;
            $userModel->save($user);

            $this->write('User "' . $user->username . '" deactivated', 'green');
        } else {
            $this->write('User "' . $user->username . '" deactivation cancelled', 'yellow');
        }
    }

    /**
     * Change the name of an existing user by username or phone
     *
     * @param string|null $username    User name to search for (optional)
     * @param string|null $phone       User phone to search for (optional)
     * @param string|null $newUsername User new name (optional)
     */
    private function changename(
        ?string $username = null,
        ?string $phone = null,
        ?string $newUsername = null
    ): void {
        $user = $this->findUser('Change username', $username, $phone);

        if ($newUsername === null) {
            $newUsername = $this->prompt('New username', null, $this->validationRules['username']['rules']);
        } else {
            // Run validation if the user has passed username and/or phone via command line
            $validation = Services::validation();
            $validation->setRules([
                'username' => $this->validationRules['username'],
            ]);

            if (! $validation->run(['username' => $newUsername])) {
                foreach ($validation->getErrors() as $message) {
                    $this->write($message, 'red');
                }

                throw new CancelException('User name change aborted');
            }
        }

        $userModel = model(UserModel::class);

        $oldUsername    = $user->username;
        $user->username = $newUsername;
        $userModel->save($user);

        $this->write('Username "' . $oldUsername . '" changed to "' . $newUsername . '"', 'green');
    }

    /**
     * Change the phone of an existing user by username or phone
     *
     * @param string|null $username User name to search for (optional)
     * @param string|null $phone    User phone to search for (optional)
     * @param string|null $newPhone User new phone (optional)
     */
    private function changephone(
        ?string $username = null,
        ?string $phone = null,
        ?string $newPhone = null
    ): void {
        $user = $this->findUser('Change phone', $username, $phone);

        if ($newPhone === null) {
            $newPhone = $this->prompt('New phone', null, $this->validationRules['phone']['rules']);
        } else {
            // Run validation if the user has passed username and/or phone via command line
            $validation = Services::validation();
            $validation->setRules([
                'phone' => $this->validationRules['phone'],
            ]);

            if (! $validation->run(['phone' => $newPhone])) {
                foreach ($validation->getErrors() as $message) {
                    $this->write($message, 'red');
                }

                throw new CancelException('User phone change aborted');
            }
        }

        $userModel = model(UserModel::class);

        $user->phone = $newPhone;
        $userModel->save($user);

        $this->write('Phone for "' . $user->username . '" changed to ' . $newPhone, 'green');
    }

    /**
     * Delete an existing user by username or phone
     *
     * @param int         $userid   User id to delete (optional)
     * @param string|null $username User name to search for (optional)
     * @param string|null $phone    User phone to search for (optional)
     */
    private function delete(int $userid = 0, ?string $username = null, ?string $phone = null): void
    {
        $userModel = model(UserModel::class);

        if ($userid !== 0) {
            $user = $userModel->findById($userid);

            $this->checkUserExists($user);
        } else {
            $user = $this->findUser('Delete user', $username, $phone);
        }

        $confirm = $this->prompt(
            'Delete the user "' . $user->username . '" (' . $user->phone . ') ?',
            ['y', 'n']
        );

        if ($confirm === 'y') {
            $userModel->delete($user->id, true);

            $this->write('User "' . $user->username . '" deleted', 'green');
        } else {
            $this->write('User "' . $user->username . '" deletion cancelled', 'yellow');
        }
    }

    /**
     * @param UserEntity|null $user
     */
    private function checkUserExists($user): void
    {
        if ($user === null) {
            throw new UserNotFoundException("User doesn't exist");
        }
    }

    /**
     * Change the password of an existing user by username or phone
     *
     * @param string|null $username User name to search for (optional)
     * @param string|null $phone    User phone to search for (optional)
     */
    private function password($username = null, $phone = null): void
    {
        $user = $this->findUser('Change user password', $username, $phone);

        $confirm = $this->prompt('Set the password for "' . $user->username . '" ?', ['y', 'n']);

        if ($confirm === 'y') {
            $password = $this->prompt(
                'Password',
                null,
                $this->validationRules['password']['rules']
            );
            $passwordConfirm = $this->prompt(
                'Password confirmation',
                null,
                $this->validationRules['password']['rules']
            );

            if ($password !== $passwordConfirm) {
                throw new BadInputException("The passwords don't match");
            }

            $userModel = model(UserModel::class);

            $user->password = $password;
            $userModel->save($user);

            $this->write('Password for "' . $user->username . '" set', 'green');
        } else {
            $this->write('Password setting for "' . $user->username . '" cancelled', 'yellow');
        }
    }

    /**
     * List users searching by username or phone
     *
     * @param string|null $username User name to search for (optional)
     * @param string|null $phone    User phone to search for (optional)
     */
    private function list(?string $username = null, ?string $phone = null): void
    {
        $userModel = model(UserModel::class);
        $userModel
            ->select($this->tables['users'] . '.id as id, username, secret as phone')
            ->join(
                $this->tables['identities'],
                $this->tables['users'] . '.id = ' . $this->tables['identities'] . '.user_id',
                'LEFT'
            )
            ->groupStart()
            ->where($this->tables['identities'] . '.type', Session::ID_TYPE_PHONE_PASSWORD)
            ->orGroupStart()
            ->where($this->tables['identities'] . '.type', null)
            ->groupEnd()
            ->groupEnd()
            ->asArray();

        if ($username !== null) {
            $userModel->like('username', $username);
        }
        if ($phone !== null) {
            $userModel->like('secret', $phone);
        }

        $this->write("Id\tUser");

        foreach ($userModel->findAll() as $user) {
            $this->write($user['id'] . "\t" . $user['username'] . ' (' . $user['phone'] . ')');
        }
    }

    /**
     * Add a user by username or phone to a group
     *
     * @param string|null $group    Group to add user to
     * @param string|null $username User name to search for (optional)
     * @param string|null $phone    User phone to search for (optional)
     */
    private function addgroup($group = null, $username = null, $phone = null): void
    {
        if ($group === null) {
            $group = $this->prompt('Group', null, 'required');
        }

        $user = $this->findUser('Add user to group', $username, $phone);

        $confirm = $this->prompt(
            'Add the user "' . $user->username . '" to the group "' . $group . '" ?',
            ['y', 'n']
        );

        if ($confirm === 'y') {
            $user->addGroup($group);

            $this->write('User "' . $user->username . '" added to group "' . $group . '"', 'green');
        } else {
            $this->write(
                'Addition of the user "' . $user->username . '" to the group "' . $group . '" cancelled',
                'yellow'
            );
        }
    }

    /**
     * Remove a user by username or phone from a group
     *
     * @param string|null $group    Group to remove user from
     * @param string|null $username User name to search for (optional)
     * @param string|null $phone    User phone to search for (optional)
     */
    private function removegroup($group = null, $username = null, $phone = null): void
    {
        if ($group === null) {
            $group = $this->prompt('Group', null, 'required');
        }

        $user = $this->findUser('Remove user from group', $username, $phone);

        $confirm = $this->prompt(
            'Remove the user "' . $user->username . '" from the group "' . $group . '" ?',
            ['y', 'n']
        );

        if ($confirm === 'y') {
            $user->removeGroup($group);

            $this->write('User "' . $user->username . '" removed from group "' . $group . '"', 'green');
        } else {
            $this->write('Removal of the user "' . $user->username . '" from the group "' . $group . '" cancelled', 'yellow');
        }
    }

    /**
     * Find an existing user by username or phone.
     *
     * @param string      $question Initial question at user prompt
     * @param string|null $username User name to search for (optional)
     * @param string|null $phone    User phone to search for (optional)
     */
    private function findUser($question = '', $username = null, $phone = null): UserEntity
    {
        if ($username === null && $phone === null) {
            $choice = $this->prompt($question . ' by username or phone ?', ['u', 'e']);

            if ($choice === 'u') {
                $username = $this->prompt('Username', null, 'required');
            } elseif ($choice === 'e') {
                $phone = $this->prompt(
                    'Phone',
                    null,
                    'required'
                );
            }
        }

        $userModel = model(UserModel::class);
        $userModel
            ->select($this->tables['users'] . '.id as id, username, secret')
            ->join(
                $this->tables['identities'],
                $this->tables['users'] . '.id = ' . $this->tables['identities'] . '.user_id',
                'LEFT'
            )
            ->groupStart()
            ->where($this->tables['identities'] . '.type', Session::ID_TYPE_PHONE_PASSWORD)
            ->orGroupStart()
            ->where($this->tables['identities'] . '.type', null)
            ->groupEnd()
            ->groupEnd()
            ->asArray();

        $user = null;
        if ($username !== null) {
            $user = $userModel->where('username', $username)->first();
        } elseif ($phone !== null) {
            $user = $userModel->where('secret', $phone)->first();
        }

        $this->checkUserExists($user);

        return $userModel->findById($user['id']);
    }
}
