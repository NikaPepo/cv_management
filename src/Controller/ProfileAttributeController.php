<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\SetProfileAttributeDto;
use App\Entity\AttributeDefinition;
use App\Entity\ProfileAttribute;
use App\Entity\User;
use App\Repository\AttributeDefinitionRepository;
use App\Repository\ProfileAttributeRepository;
use App\Repository\ProfileRepository;
use App\Service\ProfileAttributeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/profile-attributes')]
final class ProfileAttributeController extends AbstractController
{
    public function __construct(
        private readonly ProfileAttributeService $service,
        private readonly ProfileAttributeRepository $profileAttributeRepository,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly ProfileRepository $profileRepository,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        $rows = $this->profileAttributeRepository->findAllForProfile($this->requireProfile($user));
        return $this->json(array_map([self::class, 'present'], $rows));
    }

    #[Route('', methods: ['POST'])]
    public function set(
        #[MapRequestPayload] SetProfileAttributeDto $dto,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $profile = $this->requireProfile($user);
        $row = $this->service->setValue($profile, $dto);
        return $this->json(self::present($row), Response::HTTP_OK);
    }

    #[Route('/{definitionId}', methods: ['DELETE'], requirements: ['definitionId' => '\d+'])]
    public function remove(int $definitionId, #[CurrentUser] User $user): JsonResponse
    {
        $profile = $this->requireProfile($user);
        $definition = $this->attributeDefinitionRepository->find($definitionId);
        if ($definition === null) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->service->remove($profile, $definition);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    public static function present(ProfileAttribute $pa): array
    {
        $def = $pa->getAttributeDefinition();
        $payload = [
            'attributeDefinitionId' => $def->getId(),
            'name' => $def->getName(),
            'dataType' => $def->getDataType()->value,
            'required' => $def->isRequired(),
            'empty' => $pa->isEmpty(),
            'version' => $pa->getVersion(),
        ];

        match ($def->getDataType()) {
            \App\Enum\AttributeDataType::String => $payload['value'] = $pa->getStringValue(),
            \App\Enum\AttributeDataType::Text => $payload['value'] = $pa->getMarkdownText(),
            \App\Enum\AttributeDataType::Numeric => $payload['value'] = $pa->getNumericValue(),
            \App\Enum\AttributeDataType::Date => $payload['value'] = $pa->getDateValue()?->format('Y-m-d'),
            \App\Enum\AttributeDataType::Period => $payload['value'] = [
                'start' => $pa->getPeriodStart()?->format('Y-m-d'),
                'end' => $pa->getPeriodEnd()?->format('Y-m-d'),
            ],
            \App\Enum\AttributeDataType::Boolean => $payload['value'] = $pa->getBooleanValue(),
            \App\Enum\AttributeDataType::Image => $payload['value'] = $pa->getImageUrl(),
            \App\Enum\AttributeDataType::OneOfMany => $payload['value'] = $pa->getSelectedOption()?->getValue(),
        };

        return $payload;
    }

    private function requireProfile(User $user): \App\Entity\Profile
    {
        // Auto-create Profile on demand so users from any auth path
        // (form, OAuth, raw SQL insert) always have a Profile row to
        // attach ProfileAttributes / Projects / CVs to.
        return $this->profileRepository->ensureForUser($user);
    }
}