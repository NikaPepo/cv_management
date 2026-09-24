<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\AdminUpdateUserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserAdminService;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/admin/users')]
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserAdminService $adminService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Admin role required.'], Response::HTTP_FORBIDDEN);
        }

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(100, max(1, $request->query->getInt('perPage', 20)));

        $qb = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);
        $items = array_map(static fn (User $u) => self::present($u), iterator_to_array($paginator->getIterator()));
        $total = count($paginator);

        return $this->json([
            'items' => $items,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
        ]);
    }

    #[Route('/{id}', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patch(
        int $id,
        #[MapRequestPayload] AdminUpdateUserDto $dto,
        #[CurrentUser] ?User $admin,
    ): JsonResponse {
        if ($admin === null || !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Admin role required.'], Response::HTTP_FORBIDDEN);
        }
        $target = $this->userRepository->find($id);
        if ($target === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->adminService->applyUpdate($target, $dto, $admin);
        return $this->json(self::present($target));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, #[CurrentUser] ?User $admin): JsonResponse
    {
        if ($admin === null || !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Admin role required.'], Response::HTTP_FORBIDDEN);
        }
        $target = $this->userRepository->find($id);
        if ($target === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->adminService->delete($target, $admin);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    public static function present(User $u): array
    {
        return [
            'id' => $u->getId(),
            'email' => $u->getEmail(),
            'roles' => $u->getRoles(),
            'isVerified' => $u->isVerified(),
            'isBlocked' => $u->isBlocked(),
        ];
    }
}