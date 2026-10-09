<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Position;
use App\Entity\PositionApiToken;
use App\Repository\PositionApiTokenRepository;

/**
 * Generates, revokes, and validates Position-bound API tokens for the
 * Odoo integration.
 *
 * Security model:
 *  - The plaintext secret is generated with `random_bytes(32)` and
 *    returned to the caller exactly once (at creation). Only its
 *    SHA-256 hash is persisted; the hash has a UNIQUE index.
 *  - Authentication is done as a parameterised SQL lookup against the
 *    unique index. The SQL comparison is not documented as
 *    constant-time; high-entropy input plus a unique-index lookup
 *    make any timing leak practically uninteresting, but a formal
 *    `hash_equals` over the full hash was not added because it
 *    would not add a meaningful property on top of the unique-index
 *    lookup that already prunes to exactly one row (or none).
 *  - All four failure modes (missing, malformed, unknown, revoked
 *    token) return the same 401 body so an attacker cannot probe
 *    which tokens or Positions exist.
 */
final readonly class PositionApiTokenService
{
    public function __construct(
        private PositionApiTokenRepository $repository,
    ) {
    }

    /**
     * Generate a new token for the given Position. The returned
     * `secret` is the plaintext, shown to the caller only this once;
     * it is never stored and cannot be retrieved later.
     *
     * @return array{token: PositionApiToken, secret: string}
     */
    public function generate(Position $position, ?string $label = null): array
    {
        // 32 bytes of cryptographic randomness; 64 hex chars after the
        // human-grep-able "pst_" prefix. 256 bits of entropy.
        $secret = PositionApiToken::PREFIX . bin2hex(random_bytes(32));

        $token = new PositionApiToken();
        $token->setPosition($position);
        $token->setTokenHash(hash('sha256', $secret));
        $token->setPrefix(substr($secret, 0, 8));
        $token->setLabel($label);

        $this->repository->save($token);

        return ['token' => $token, 'secret' => $secret];
    }

    /**
     * Resolve a plaintext secret into its token, or null if the secret
     * is invalid, unknown, or revoked. See the class docblock for the
     * security rationale.
     */
    public function findActiveBySecret(string $secret): ?PositionApiToken
    {
        $hash = hash('sha256', $secret);
        return $this->repository->findActiveByHash($hash);
    }

    /**
     * Mark a token as freshly used. Cheap fire-and-forget; we don't
     * care if the UPDATE fails because the row was just revoked.
     */
    public function markUsed(PositionApiToken $token): void
    {
        $token->touchLastUsed();
        $this->repository->save($token);
    }

    public function revoke(PositionApiToken $token): void
    {
        if ($token->isRevoked()) {
            return;
        }
        $token->revoke();
        $this->repository->save($token);
    }

    /**
     * Hard-delete a token that has already been revoked. Active tokens
     * are refused — callers must revoke first. This is a permanent
     * operation: the row is removed, so the SHA-256 hash is gone and
     * the secret becomes unusable (which is the point — it was already
     * unusable via the active-by-hash check).
     *
     * @throws \DomainException if the token is still active
     */
    public function deleteRevoked(PositionApiToken $token): void
    {
        if (!$token->isRevoked()) {
            throw new \DomainException('Token must be revoked before it can be deleted.');
        }
        $this->repository->delete($token);
    }

    /**
     * @return PositionApiToken[]
     */
    public function listForPosition(Position $position): array
    {
        return $this->repository->findAllForPosition($position);
    }
}
