<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\AttributeLookupQueryDto;
use App\DTO\CreateAttributeCategoryDto;
use App\DTO\CreateAttributeDefinitionDto;
use App\DTO\UpdateAttributeDefinitionDto;
use App\Entity\AttributeDefinition;
use App\Entity\User;
use App\Repository\AttributeCategoryRepository;
use App\Repository\AttributeDefinitionRepository;
use App\Service\AttributeDefinitionService;
use App\Service\AttributeLookupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/attributes')]
final class AttributeDefinitionController extends AbstractController
{
    public function __construct(
        private readonly AttributeDefinitionService $service,
        private readonly AttributeLookupService $lookup,
        private readonly AttributeDefinitionRepository $attributeRepo,
        private readonly AttributeCategoryRepository $categoryRepo,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $rows = $this->attributeRepo->findBy([], ['name' => 'ASC']);
        return $this->json(array_map([self::class, 'present'], $rows));
    }

    #[Route('/lookup', methods: ['GET'])]
    public function lookupEndpoint(
        #[MapQueryString] AttributeLookupQueryDto $query,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        $profile = $user?->getProfile();
        $result = $this->lookup->lookup($query, $profile);
        return $this->json([
            'prefix' => array_map([self::class, 'present'], $result['prefix']),
            'byCategory' => array_map([self::class, 'present'], $result['byCategory']),
            'recent' => array_map([self::class, 'present'], $result['recent']),
        ]);
    }

    #[Route('/categories', methods: ['GET'])]
    public function listCategories(): JsonResponse
    {
        $categories = $this->categoryRepo->findAllOrdered();
        return $this->json(array_map(static fn ($c) => [
            'id' => $c->getId(),
            'code' => $c->getCode(),
            'name' => $c->getName(),
            'sortOrder' => $c->getSortOrder(),
        ], $categories));
    }

    #[Route('/categories', methods: ['POST'])]
    public function createCategory(
        #[MapRequestPayload] CreateAttributeCategoryDto $dto,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if (!$this->isGranted('ROLE_RECRUITER')) {
            return $this->json(['error' => 'Recruiter role required.'], Response::HTTP_FORBIDDEN);
        }
        $category = $this->service->createCategory($dto);
        return $this->json([
            'id' => $category->getId(),
            'code' => $category->getCode(),
            'name' => $category->getName(),
            'sortOrder' => $category->getSortOrder(),
        ], Response::HTTP_CREATED);
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateAttributeDefinitionDto $dto,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if (!$this->isGranted('ROLE_RECRUITER')) {
            return $this->json(['error' => 'Recruiter role required.'], Response::HTTP_FORBIDDEN);
        }
        $attribute = $this->service->create($dto);
        return $this->json(self::present($attribute), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $attribute = $this->attributeRepo->find($id);
        if ($attribute === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        return $this->json(self::present($attribute, withOptions: true));
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateAttributeDefinitionDto $dto,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if (!$this->isGranted('ROLE_RECRUITER')) {
            return $this->json(['error' => 'Recruiter role required.'], Response::HTTP_FORBIDDEN);
        }
        $attribute = $this->attributeRepo->find($id);
        if ($attribute === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->service->update($attribute, $dto);
        return $this->json(self::present($attribute, withOptions: true));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$this->isGranted('ROLE_RECRUITER')) {
            return $this->json(['error' => 'Recruiter role required.'], Response::HTTP_FORBIDDEN);
        }
        $attribute = $this->attributeRepo->find($id);
        if ($attribute === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->service->delete($attribute);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    public static function present(AttributeDefinition $a, bool $withOptions = false): array
    {
        $data = [
            'id' => $a->getId(),
            'name' => $a->getName(),
            'description' => $a->getDescription(),
            'dataType' => $a->getDataType()->value,
            'categoryId' => $a->getCategory()->getId(),
            'categoryName' => $a->getCategory()->getName(),
            'required' => $a->isRequired(),
        ];

        if ($withOptions || $a->getDataType()->value === 'one_of_many') {
            $data['options'] = array_map(static fn ($o) => [
                'id' => $o->getId(),
                'value' => $o->getValue(),
                'sortOrder' => $o->getSortOrder(),
            ], $a->getOptions()->toArray());
        }

        return $data;
    }
}