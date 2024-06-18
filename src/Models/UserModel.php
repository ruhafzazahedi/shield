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

namespace CodeIgniter\Shield\Models;

use CodeIgniter\Database\Exceptions\DataException;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Entities\UserIdentity;
use CodeIgniter\Shield\Exceptions\InvalidArgumentException;
use CodeIgniter\Shield\Exceptions\LogicException;
use CodeIgniter\Shield\Exceptions\ValidationException;
use Faker\Generator;

/**
 * @phpstan-consistent-constructor
 */
class UserModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = User::class;
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'username',
        'status',
        'status_message',
        'active',
        'last_active',
    ];
    protected $useTimestamps = true;
    protected $afterFind     = ['fetchIdentities'];
    protected $afterInsert   = ['savePhoneIdentity'];
    protected $afterUpdate   = ['savePhoneIdentity'];

    /**
     * Whether identity records should be included
     * when user records are fetched from the database.
     */
    protected bool $fetchIdentities = false;

    /**
     * Save the User for afterInsert and afterUpdate
     */
    protected ?User $tempUser = null;

    protected function initialize(): void
    {
        parent::initialize();

        $this->table = $this->tables['users'];
    }

    /**
     * Mark the next find* query to include identities
     *
     * @return $this
     */
    public function withIdentities(): self
    {
        $this->fetchIdentities = true;

        return $this;
    }

    /**
     * Populates identities for all records
     * returned from a find* method. Called
     * automatically when $this->fetchIdentities == true
     *
     * Model event callback called by `afterFind`.
     */
    protected function fetchIdentities(array $data): array
    {
        if (! $this->fetchIdentities) {
            return $data;
        }

        $userIds = $data['singleton']
            ? array_column($data, 'id')
            : array_column($data['data'], 'id');

        if ($userIds === []) {
            return $data;
        }

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        // Get our identities for all users
        $identities = $identityModel->getIdentitiesByUserIds($userIds);

        if (empty($identities)) {
            return $data;
        }

        $mappedUsers = $this->assignIdentities($data, $identities);

        $data['data'] = $data['singleton'] ? $mappedUsers[$data['id']] : $mappedUsers;

        return $data;
    }

    /**
     * Map our users by ID to make assigning simpler
     *
     * @param array              $data       Event $data
     * @param list<UserIdentity> $identities
     *
     * @return         list<User>              UserId => User object
     * @phpstan-return array<int|string, User> UserId => User object
     */
    private function assignIdentities(array $data, array $identities): array
    {
        $mappedUsers    = [];
        $userIdentities = [];

        $users = $data['singleton'] ? [$data['data']] : $data['data'];

        foreach ($users as $user) {
            $mappedUsers[$user->id] = $user;
        }
        unset($users);

        // Now group the identities by user
        foreach ($identities as $identity) {
            $userIdentities[$identity->user_id][] = $identity;
        }
        unset($identities);

        // Now assign the identities to the user
        foreach ($userIdentities as $userId => $identityArray) {
            $mappedUsers[$userId]->identities = $identityArray;
        }
        unset($userIdentities);

        return $mappedUsers;
    }

    /**
     * Adds a user to the default group.
     * Used during registration.
     */
    public function addToDefaultGroup(User $user): void
    {
        $defaultGroup  = setting('AuthGroups.defaultGroup');
        $allowedGroups = array_keys(setting('AuthGroups.groups'));

        if (empty($defaultGroup) || ! in_array($defaultGroup, $allowedGroups, true)) {
            throw new InvalidArgumentException(lang('Auth.unknownGroup', [$defaultGroup ?? '--not found--']));
        }

        $user->addGroup($defaultGroup);
    }

    public function fake(Generator &$faker): User
    {
        $this->checkReturnType();

        return new $this->returnType([
            'username' => $faker->unique()->userName(),
            'active'   => true,
        ]);
    }

    /**
     * Locates a User object by ID.
     *
     * @param int|string $id
     */
    public function findById($id): ?User
    {
        return $this->find($id);
    }

    /**
     * Locate a User object by the given credentials.
     *
     * @param array<string, string> $credentials
     */
    public function findByCredentials(array $credentials): ?User
    {
        // Phone is stored in an identity so remove that here
        $phone = $credentials['phone'] ?? null;
        unset($credentials['phone']);

        if ($phone === null && $credentials === []) {
            return null;
        }

        // any of the credentials used should be case-insensitive
        foreach ($credentials as $key => $value) {
            $this->where(
                'LOWER(' . $this->db->protectIdentifiers($this->table . ".{$key}") . ')',
                strtolower($value)
            );
        }

        if ($phone !== null) {
            /** @var array<string, int|string|null>|null $data */
            $data = $this->select(
                sprintf('%1$s.*, %2$s.secret as phone, %2$s.secret2 as password_hash', $this->table, $this->tables['identities'])
            )
                ->join($this->tables['identities'], sprintf('%1$s.user_id = %2$s.id', $this->tables['identities'], $this->table))
                ->where($this->tables['identities'] . '.type', Session::ID_TYPE_PHONE_PASSWORD)
                ->where(
                    'LOWER(' . $this->db->protectIdentifiers($this->tables['identities'] . '.secret') . ')',
                    strtolower($phone)
                )
                ->asArray()
                ->first();

            if ($data === null) {
                return null;
            }

            $phone = $data['phone'];
            unset($data['phone']);
            $password_hash = $data['password_hash'];
            unset($data['password_hash']);

            $this->checkReturnType();

            $user                = new $this->returnType($data);
            $user->phone         = $phone;
            $user->password_hash = $password_hash;
            $user->syncOriginal();

            return $user;
        }

        return $this->first();
    }

    /**
     * Activate a User.
     */
    public function activate(User $user): void
    {
        $user->active = true;

        $this->save($user);
    }

    /**
     * Override the BaseModel's `insert()` method.
     * If you pass User object, also inserts Phone Identity.
     *
     * @param array|User $row
     *
     * @return int|string|true Insert ID if $returnID is true
     *
     * @throws ValidationException
     */
    public function insert($row = null, bool $returnID = true)
    {
        // Clone User object for not changing the passed object.
        $this->tempUser = $row instanceof User ? clone $row : null;

        $result = parent::insert($row, $returnID);

        $this->checkQueryReturn($result);

        return $returnID ? $this->insertID : $result;
    }

    /**
     * Override the BaseModel's `update()` method.
     * If you pass User object, also updates Phone Identity.
     *
     * @param array|int|string|null $id
     * @param array|User            $row
     *
     * @return true if the update is successful
     *
     * @throws ValidationException
     */
    public function update($id = null, $row = null): bool
    {
        // Clone User object for not changing the passed object.
        $this->tempUser = $row instanceof User ? clone $row : null;

        try {
            /** @throws DataException */
            $result = parent::update($id, $row);
        } catch (DataException $e) {
            // When $data is an array.
            if ($this->tempUser === null) {
                throw $e;
            }

            $messages = [
                lang('Database.emptyDataset', ['update']),
            ];

            if (in_array($e->getMessage(), $messages, true)) {
                $this->tempUser->savePhoneIdentity();

                return true;
            }

            throw $e;
        }

        $this->checkQueryReturn($result);

        return true;
    }

    /**
     * Override the BaseModel's `save()` method.
     * If you pass User object, also updates Phone Identity.
     *
     * @param array|User $row
     *
     * @return true if the save is successful
     *
     * @throws ValidationException
     */
    public function save($row): bool
    {
        $result = parent::save($row);

        $this->checkQueryReturn($result);

        return true;
    }

    /**
     * Save Phone Identity
     *
     * Model event callback called by `afterInsert` and `afterUpdate`.
     */
    protected function savePhoneIdentity(array $data): array
    {
        // If insert()/update() gets an array data, do nothing.
        if ($this->tempUser === null) {
            return $data;
        }

        // Insert
        if ($this->tempUser->id === null) {
            /** @var User $user */
            $user = $this->find($this->db->insertID());

            // If you get identity (phone/password), the User object must have the id.
            $this->tempUser->id = $user->id;

            $user->phone         = $this->tempUser->phone ?? '';
            $user->password      = $this->tempUser->password ?? '';
            $user->password_hash = $this->tempUser->password_hash ?? '';

            $user->savePhoneIdentity();
            $this->tempUser = null;

            return $data;
        }

        // Update
        $this->tempUser->savePhoneIdentity();
        $this->tempUser = null;

        return $data;
    }

    /**
     * Updates the user's last active date.
     */
    public function updateActiveDate(User $user): void
    {
        assert($user->last_active instanceof Time);

        // Safe date string for database
        $last_active = $this->timeToDate($user->last_active);

        $this->builder()
            ->set('last_active', $last_active)
            ->where('id', $user->id)
            ->update();
    }

    private function checkReturnType(): void
    {
        if (! is_a($this->returnType, User::class, true)) {
            throw new LogicException('Return type must be a subclass of ' . User::class);
        }
    }
}
