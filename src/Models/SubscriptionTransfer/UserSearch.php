<?php declare(strict_types=1);

namespace Crm\SubscriptionsModule\Models\SubscriptionTransfer;

use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;

class UserSearch
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
    ) {
    }

    /**
     * @return ActiveRow[]
     */
    public function search(string $searchTerm, int $currentUserId): array
    {
        $foundUsers = [
            ...$this->findById($searchTerm),
            ...$this->findByEmail($searchTerm),
        ];

        $foundUsersWithoutCurrentOne = array_filter($foundUsers, function (ActiveRow $user) use ($currentUserId) {
            return $user->id !== $currentUserId;
        });

        return $foundUsersWithoutCurrentOne;
    }

    /**
     * @return ActiveRow[]
     */
    private function findById(string $searchTerm): array
    {
        if (!is_numeric($searchTerm)) {
            return [];
        }

        $user = $this->usersRepository->find($searchTerm);
        if (!$user) {
            return [];
        }

        return [$user];
    }

    /**
     * @return ActiveRow[]
     */
    private function findByEmail(string $searchTerm): array
    {
        if (strlen($searchTerm) < 3) {
            return [];
        }

        return $this->usersRepository->getTable()
            ->where('email LIKE ?', sprintf('%s%%', $searchTerm))
            ->limit(15)
            ->fetchAll();
    }
}
