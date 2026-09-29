<?php

declare(strict_types=1);

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests for the logout method restriction listener.
 *
 * Boots the kernel just enough to hit /api/logout via KernelBrowser.
 * We don't log in — the firewall's LogoutListener still runs on POST
 * (with an anonymous token) and Symfony responds with a redirect to
 * /api/logout-success (HTTP 302). What matters here is that non-POST
 * methods are short-circuited by our listener with HTTP 405, never
 * reaching the firewall.
 *
 * The 405 must include an Allow: POST header per RFC 9110 §15.5.6.
 */
final class LogoutMethodRestrictionListenerTest extends WebTestCase
{
    private const PATH = '/api/logout';

    private function client(): KernelBrowser
    {
        return self::createClient();
    }

    public function testGetReturns405(): void
    {
        $client = $this->client();
        $client->request('GET', self::PATH);

        self::assertSame(405, $client->getResponse()->getStatusCode());
        self::assertSame('POST', $client->getResponse()->headers->get('Allow'));
    }

    public function testPutReturns405(): void
    {
        $client = $this->client();
        $client->request('PUT', self::PATH);

        self::assertSame(405, $client->getResponse()->getStatusCode());
    }

    public function testPatchReturns405(): void
    {
        $client = $this->client();
        $client->request('PATCH', self::PATH);

        self::assertSame(405, $client->getResponse()->getStatusCode());
    }

    public function testDeleteReturns405(): void
    {
        $client = $this->client();
        $client->request('DELETE', self::PATH);

        self::assertSame(405, $client->getResponse()->getStatusCode());
    }

    public function testOptionsReturns405(): void
    {
        $client = $this->client();
        $client->request('OPTIONS', self::PATH);

        self::assertSame(405, $client->getResponse()->getStatusCode());
    }

    public function testPostIsAcceptedByListener(): void
    {
        $client = $this->client();
        $client->request('POST', self::PATH);

        // The listener must NOT short-circuit POST. From here the firewall
        // takes over. With an anonymous token, the firewall logout runs,
        // destroys the (empty) session, and redirects to api_logout_success.
        // Either a 302 redirect or a successful logout response is fine —
        // what we verify is that 405 is NOT returned.
        self::assertNotSame(
            405,
            $client->getResponse()->getStatusCode(),
            'POST must not be blocked by the method restriction listener.'
        );
    }

    public function testNonLogoutPathIsUnaffected(): void
    {
        $client = $this->client();
        // /api/login accepts POST (json_login authenticator) — using it here
        // proves the listener doesn't produce 405 for paths other than
        // /api/logout. json_login will reject the empty body with 400/401,
        // never 405. Symfony's router would also reject POST to a GET-only
        // route (/api/main-page, /api/me) with its own 405, so we don't
        // use those — they're ambiguous with our listener's 405.
        $client->request('POST', '/api/login', server: [
            'CONTENT_TYPE' => 'application/json',
        ]);

        self::assertNotSame(
            405,
            $client->getResponse()->getStatusCode(),
            'Listener must only act on the configured logout path.'
        );
        // And specifically: the Allow: POST header our listener sets must
        // not be present on a non-logout response.
        self::assertNotSame(
            'POST',
            $client->getResponse()->headers->get('Allow'),
        );
    }
}
