<?php

declare(strict_types=1);

namespace Mautic\UserBundle\Tests\Controller\Api;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\UserBundle\Entity\Permission;
use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserApiControllerFunctionalTest extends MauticMysqlTestCase
{
    public function testRoleUpdateByApiGivesErrorResponseIfUserDoesNotExist(): void
    {
        // Assuming user with id 99999 does not exist
        $this->client->request(Request::METHOD_PATCH, '/api/users/99999/edit', ['role' => 1]);
        $clientResponse = $this->client->getResponse();
        Assert::assertSame(Response::HTTP_NOT_FOUND, $clientResponse->getStatusCode());
        Assert::assertStringContainsString('"message":"Item was not found."', $clientResponse->getContent());
    }

    public function testRoleUpdateByApiGivesErrorResponseIfRoleDoesNotExist(): void
    {
        // Assuming role with id 99999 does not exist
        $this->client->request(Request::METHOD_PATCH, '/api/users/1/edit', ['role' => 99999]);
        $clientResponse = $this->client->getResponse();
        Assert::assertSame(Response::HTTP_BAD_REQUEST, $clientResponse->getStatusCode());
        Assert::assertStringContainsString('"message":"role: This value is not valid."', $clientResponse->getContent());
    }

    public function testRoleUpdateByApiGivesErrorResponseWithInvalidRequestFormat(): void
    {
        // Correct request format is ['role' => 2]
        $this->client->request(Request::METHOD_PATCH, '/api/users/1/edit', ['role' => ['id' => 2]]);
        $clientResponse = $this->client->getResponse();
        Assert::assertSame(Response::HTTP_BAD_REQUEST, $clientResponse->getStatusCode());
        Assert::assertStringContainsString('"message":"role: This value is not valid."', $clientResponse->getContent());
    }

    public function testRoleUpdateByApiGivesErrorResponseIfUserDoesNotHaveValidPermissionToUpdate(): void
    {
        // Create non-admin role
        $role = $this->createRole();
        // Create permissions for the role
        $this->createPermission('lead:leads:viewown', $role, 1024);
        // Create non-admin user
        $user = $this->createUser($role);
        $this->em->flush();
        $this->em->clear();

        // Login newly created non-admin user
        $this->loginUser($user->getUsername());
        $this->client->setServerParameter('PHP_AUTH_USER', $user->getUsername());
        $this->client->setServerParameter('PHP_AUTH_PW', 'mautic');

        $this->client->request(Request::METHOD_PATCH, "/api/users/{$user->getId()}/edit", ['role' => $role->getId()]);
        $clientResponse = $this->client->getResponse();
        Assert::assertSame(Response::HTTP_FORBIDDEN, $clientResponse->getStatusCode());
        Assert::assertStringContainsString(
            '"message":"You do not have access to the requested area\/action."',
            $clientResponse->getContent()
        );
    }

    public function testRoleUpdateByApiThroughAdminUserGivesSuccessResponse(): void
    {
        // Create admin role
        $role = $this->createRole(true);
        // Create admin user
        $user = $this->createUser($role);
        $this->em->flush();
        $this->em->clear();

        // Login newly created admin user
        $this->loginUser($user->getUsername());
        $this->client->setServerParameter('PHP_AUTH_USER', $user->getUsername());
        $this->client->setServerParameter('PHP_AUTH_PW', 'mautic');

        $this->client->request(Request::METHOD_PATCH, "/api/users/{$user->getId()}/edit", ['role' => $role->getId()]);
        $clientResponse = $this->client->getResponse();
        Assert::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode());
        Assert::assertStringContainsString('"username":"'.$user->getUsername().'"', $clientResponse->getContent());
    }

    public function testRoleUpdateByApiThroughNonAdminUserGivesSuccessResponse(): void
    {
        // Create non-admin role
        $role = $this->createRole();
        // Create permissions to update user for the role
        $this->createPermission('user:users:edit', $role, 52);
        // Create non-admin user
        $user = $this->createUser($role);
        $this->em->flush();
        $this->em->clear();

        $this->loginUser($user->getUsername());
        $this->client->setServerParameter('PHP_AUTH_USER', $user->getUsername());
        $this->client->setServerParameter('PHP_AUTH_PW', 'mautic');

        $this->client->request(Request::METHOD_PATCH, "/api/users/{$user->getId()}/edit", ['role' => $role->getId()]);
        $clientResponse = $this->client->getResponse();
        Assert::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode());
        Assert::assertStringContainsString('"username":"'.$user->getUsername().'"', $clientResponse->getContent());
    }

    private function createRole(bool $isAdmin = false): Role
    {
        $role = new Role();
        $role->setName('Role');
        $role->setIsAdmin($isAdmin);
        $this->em->persist($role);

        return $role;
    }

    private function createPermission(string $rawPermission, Role $role, int $bitwise): void
    {
        $parts      = explode(':', $rawPermission);
        $permission = new Permission();
        $permission->setBundle($parts[0]);
        $permission->setName($parts[1]);
        $permission->setRole($role);
        $permission->setBitwise($bitwise);
        $this->em->persist($permission);
    }

    private function createUser(Role $role): User
    {
        $user = new User();
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setUsername('john.doe');
        $user->setEmail('john.doe@email.com');
        $encoder = self::$container->get('security.encoder_factory')->getEncoder($user);
        $user->setPassword($encoder->encodePassword('mautic', null));
        $user->setRole($role);
        $this->em->persist($user);

        return $user;
    }
}
