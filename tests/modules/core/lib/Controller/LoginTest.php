<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\core\Controller;

use ReflectionClass;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Locale\Localization;
use SimpleSAML\Module\core\Controller;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\Module\core\Auth\UserPassOrgBase;
use SimpleSAML\TestUtils\ClearStateTestCase;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Set of tests for the controllers in the "core" module.
 *
 * For now, this test extends ClearStateTestCase so that it doesn't interfere with other tests. Once every class has
 * been made PSR-7-aware, that won't be necessary any longer.
 *
 * @covers \SimpleSAML\Module\core\Controller\Login
 * @package SimpleSAML\Test
 */
class LoginTest extends ClearStateTestCase
{
    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /** @var \SimpleSAML\Configuration[] */
    protected array $loadedConfigs;


    /**
     * Set up for each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = Configuration::loadFromArray(
            [
                'baseurlpath' => 'https://example.org/simplesaml',
                'module.enable' => ['exampleauth' => true],
            ],
            '[ARRAY]',
            'simplesaml'
        );

        Configuration::setPreLoadedConfig($this->config, 'config.php');
    }


    /**
     * Test that we are presented with a regular page if we go to the landing page.
     */
    public function testWelcome(): void
    {
        $c = new Controller\Login($this->config);

        /** @var \SimpleSAML\XHTML\Template $response */
        $response = $c->welcome();

        $this->assertInstanceOf(Template::class, $response);
        $this->assertEquals('core:welcome.twig', $response->getTemplateName());
    }

    /**
     * Test basic operation of the logout controller.
     * @TODO check if the passed auth source is correctly used
     */
    public function testLogout(): void
    {
        $request = Request::create(
            '/logout',
            'GET',
            [],
        );
        $response = $c->logout($request, 'example-authsource');

        $this->assertInstanceOf(RunnableResponse::class, $response);
        $callable = $response->getCallable();
        $this->assertInstanceOf(\SimpleSAML\Auth\Simple::class, $callable[0]);
        $this->assertEquals('logout', $callable[1]);
    }


    /**
     */
    public function testLoginUserPassNoState(): void
    {
        $request = Request::create(
            '/loginuserpass',
            'GET',
            [],
        );

        $c = new Controller\Login($this->config);

        $this->expectException(Error\BadRequest::class);
        $c->loginuserpass($request);
    }


    public function testLogoutReturnToDisallowedUrlRejected(): void
    {
        $request = Request::create(
            '/logout/example-authsource',
            'GET',
            ['ReturnTo' => 'https://loeki.tv/asjemenou'],
        );
        $_SERVER['REQUEST_URI']  = 'https://example.com/simplesaml/module.php/core/logout/example-authsource';

        $c = new Controller\Login($this->config);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('URL not allowed: https://loeki.tv/asjemenou');
        $response = $c->logout($request, 'example-authsource');
    }

    public function testLogoutReturnToAllowedUrl(): void
    {
        $request = Request::create(
            '/logout/example-authsource',
            'GET',
            ['ReturnTo' => 'https://example.org/something'],
        );
        $_SERVER['REQUEST_URI']  = 'https://example.com/simplesaml/module.php/core/logout/example-authsource';

        $c = new Controller\Login($this->config);

        $response = $c->logout($request, 'example-authsource');
        $this->assertInstanceOf(RunnableResponse::class, $response);
        $this->assertEquals('https://example.org/something', $response->getArguments()[0]);
    }

    public function testClearDiscoChoicesReturnToDisallowedUrlRejected(): void
    {
        $request = Request::create(
            '/cleardiscochoices',
            'GET',
            ['ReturnTo' => 'https://loeki.tv/asjemenou'],
        );
        $_SERVER['REQUEST_URI']  = 'https://example.com/simplesaml/module.php/core/cleardiscochoices';

        $c = new Controller\Login($this->config);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('URL not allowed: https://loeki.tv/asjemenou');
        $response = $c->cleardiscochoices($request);
    }


    /**
     */
    public function testLoginUserPass(): void
    {
        $request = Request::create(
            '/loginuserpass',
            'GET',
            ['AuthState' => 'someState'],
        );

        $c = new Controller\Login($this->config);

        $c->setAuthState(new class () extends Auth\State {
            public static function loadState(string $id, string $stage, bool $allowMissing = false): ?array
            {
                return [UserPassBase::AUTHID => 'something'];
            }
        });

        $c->setAuthSource(new class () extends UserPassBase {
            public function __construct()
            {
                // stub
            }

            public function authenticate(array &$state): void
            {
                // stub
            }

            public static function getById(string $authId, ?string $type = null): ?UserPassBase
            {
                return new static();
            }

            protected function login(string $username, string $password): array
            {
                return ['mail' => 'noreply@simplesamlphp.org'];
            }
        });

        /** @var \SimpleSAML\XHTML\Template $response */
        $response = $c->loginuserpass($request);

        $this->assertInstanceOf(Template::class, $response);
        $this->assertEquals('core:loginuserpass.twig', $response->getTemplateName());
    }


    /**
     */
    public function testLoginUserPassOrgNoState(): void
    {
        $request = Request::create(
            '/loginuserpassorg',
            'GET',
            [],
        );

        $c = new Controller\Login($this->config);

        $this->expectException(Error\BadRequest::class);

        $c->loginuserpassorg($request);
    }


    /**
    public function testLoginUserPassOrg(): void
    {
        $request = Request::create(
            '/loginuserpassorg',
            'GET',
            ['AuthState' => 'someState'],
        );

        $c = new Controller\Login($this->config);

        $c->setAuthState(new class () extends Auth\State {
            public static function loadState(string $id, string $stage, bool $allowMissing = false): ?array
            {
                return [UserPassOrgBase::AUTHID => 'something'];
            }
        });

        $c->setAuthSource(new class () extends UserPassOrgBase {
            public function __construct()
            {
                // stub
            }

            public function authenticate(array &$state): void
            {
                // stub
            }

            public static function getById(string $authId, ?string $type = null): ?UserPassOrgBase
            {
                return new static();
            }

            protected function login(string $username, string $password, string $organization): array
            {
                return ['mail' => 'noreply@simplesamlphp.org'];
            }

            protected function getOrganizations(): array
            {
                return ['ssp' => 'SimpleSAMLphp'];
            }
        });

        // @var \SimpleSAML\XHTML\Template $response
        $response = $c->loginuserpassorg($request);

        $this->assertInstanceOf(Template::class, $response);
        $this->assertEquals('core:loginuserpass.twig', $response->getTemplateName());
    }
     */
>>>>>>> 051532a60 (Add some unit tests)
}
