<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class KeycloakService
{
    protected $client;
    protected $baseUrl;
    protected $adminUser;
    protected $adminPassword;
    protected $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('services.keycloak.url', 'http://localhost:8090');
        $this->adminUser = config('services.keycloak.admin_user', 'admin');
        $this->adminPassword = config('services.keycloak.admin_password', 'admin123');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'verify' => false,
            'timeout' => 30,
        ]);
    }

    /**
     * Get admin access token from Keycloak
     */
    protected function getAdminToken()
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            $response = $this->client->post('/realms/master/protocol/openid-connect/token', [
                'form_params' => [
                    'client_id' => 'admin-cli',
                    'username' => $this->adminUser,
                    'password' => $this->adminPassword,
                    'grant_type' => 'password',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $this->accessToken = $data['access_token'];

            return $this->accessToken;
        } catch (\Exception $e) {
            Log::error('Failed to get Keycloak admin token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a new realm for a tenant
     */
    public function createTenantRealm($tenantId, $tenantName)
    {
        $token = $this->getAdminToken();
        $realmId = 'tenant-' . $tenantId;

        try {
            $response = $this->client->post('/admin/realms', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'realm' => $realmId,
                    'displayName' => $tenantName,
                    'enabled' => true,
                    'sslRequired' => 'none',
                    'registrationAllowed' => false,
                    'loginWithEmailAllowed' => true,
                    'duplicateEmailsAllowed' => false,
                    'resetPasswordAllowed' => true,
                    'editUsernameAllowed' => false,
                    'bruteForceProtected' => true,
                ],
            ]);

            Log::info("Created realm: {$realmId} for tenant: {$tenantName}");
            return $realmId;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // If realm already exists (409 Conflict), just return the realm ID
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 409) {
                Log::warning("Realm {$realmId} already exists, using existing realm");
                return $realmId;
            }

            Log::error("Failed to create realm for tenant {$tenantId}: " . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            Log::error("Failed to create realm for tenant {$tenantId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add a product client to a tenant's realm
     */
    public function addProductClient($realmId, $productName, $productUrl, $redirectUris = [])
    {
        $token = $this->getAdminToken();
        $clientId = strtolower(str_replace(' ', '-', $productName));

        // Default redirect URIs if not provided
        if (empty($redirectUris)) {
            $redirectUris = [
                $productUrl . '/*',
                $productUrl . '/auth/callback',
            ];
        }

        try {
            $response = $this->client->post("/admin/realms/{$realmId}/clients", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'clientId' => $clientId,
                    'name' => $productName,
                    'enabled' => true,
                    'publicClient' => false,
                    'protocol' => 'openid-connect',
                    'standardFlowEnabled' => true,
                    'implicitFlowEnabled' => false,
                    'directAccessGrantsEnabled' => true,
                    'serviceAccountsEnabled' => true,
                    'authorizationServicesEnabled' => true,
                    'redirectUris' => $redirectUris,
                    'webOrigins' => ['*'],
                    'attributes' => [
                        'access.token.lifespan' => '3600',
                        'client.session.idle.timeout' => '3600',
                        'client.session.max.lifespan' => '86400',
                    ],
                ],
            ]);

            // Get client UUID
            $clientUuid = $this->getClientUuid($realmId, $clientId);

            // Generate client secret
            $secret = $this->regenerateClientSecret($realmId, $clientUuid);

            Log::info("Created client {$clientId} in realm {$realmId}");

            return [
                'client_id' => $clientId,
                'client_uuid' => $clientUuid,
                'client_secret' => $secret,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to create client {$productName} in realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get client UUID by client ID
     */
    protected function getClientUuid($realmId, $clientId)
    {
        $token = $this->getAdminToken();

        try {
            $response = $this->client->get("/admin/realms/{$realmId}/clients", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'query' => [
                    'clientId' => $clientId,
                ],
            ]);

            $clients = json_decode($response->getBody()->getContents(), true);

            if (empty($clients)) {
                throw new \Exception("Client {$clientId} not found in realm {$realmId}");
            }

            return $clients[0]['id'];
        } catch (\Exception $e) {
            Log::error("Failed to get client UUID for {$clientId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Regenerate client secret
     */
    protected function regenerateClientSecret($realmId, $clientUuid)
    {
        $token = $this->getAdminToken();

        try {
            $response = $this->client->post("/admin/realms/{$realmId}/clients/{$clientUuid}/client-secret", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['value'];
        } catch (\Exception $e) {
            Log::error("Failed to regenerate client secret: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync product roles to client
     */
    public function syncProductRoles($realmId, $clientId, array $roles)
    {
        $token = $this->getAdminToken();
        $clientUuid = $this->getClientUuid($realmId, $clientId);

        try {
            // Get existing client roles
            $existingRoles = $this->getClientRoles($realmId, $clientUuid);
            $existingRoleNames = array_column($existingRoles, 'name');

            // Create new roles
            foreach ($roles as $role) {
                if (!in_array($role['name'], $existingRoleNames)) {
                    $this->createClientRole($realmId, $clientUuid, $role);
                }
            }

            Log::info("Synced " . count($roles) . " roles for client {$clientId} in realm {$realmId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to sync roles for client {$clientId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get client roles
     */
    protected function getClientRoles($realmId, $clientUuid)
    {
        $token = $this->getAdminToken();

        try {
            $response = $this->client->get("/admin/realms/{$realmId}/clients/{$clientUuid}/roles", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error("Failed to get client roles: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a client role
     */
    protected function createClientRole($realmId, $clientUuid, $role)
    {
        $token = $this->getAdminToken();

        try {
            $this->client->post("/admin/realms/{$realmId}/clients/{$clientUuid}/roles", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'name' => $role['name'],
                    'description' => $role['description'] ?? '',
                    'clientRole' => true,
                ],
            ]);

            Log::info("Created role {$role['name']} for client in realm {$realmId}");
        } catch (\Exception $e) {
            Log::error("Failed to create role {$role['name']}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a user in tenant realm
     */
    public function createUser($realmId, array $userData)
    {
        $token = $this->getAdminToken();

        try {
            $response = $this->client->post("/admin/realms/{$realmId}/users", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'username' => $userData['username'],
                    'email' => $userData['email'],
                    'firstName' => $userData['first_name'] ?? '',
                    'lastName' => $userData['last_name'] ?? '',
                    'enabled' => $userData['enabled'] ?? true,
                    'emailVerified' => $userData['email_verified'] ?? false,
                    'credentials' => isset($userData['password']) ? [[
                        'type' => 'password',
                        'value' => $userData['password'],
                        'temporary' => $userData['temporary_password'] ?? false,
                    ]] : [],
                ],
            ]);

            Log::info("Created user {$userData['username']} in realm {$realmId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create user in realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a realm
     */
    public function deleteRealm($realmId)
    {
        $token = $this->getAdminToken();

        try {
            $this->client->delete("/admin/realms/{$realmId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            Log::info("Deleted realm: {$realmId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fix security-admin-console client settings for a realm
     * This enables proper login to Admin Console
     */
    public function fixAdminConsoleClient($realmId)
    {
        try {
            $token = $this->getAdminToken();

            // Get security-admin-console client
            $clientsResponse = $this->client->get("/admin/realms/$realmId/clients", [
                'headers' => ['Authorization' => "Bearer $token"],
            ]);

            $clients = json_decode($clientsResponse->getBody(), true);
            $adminConsoleClient = null;

            foreach ($clients as $c) {
                if ($c['clientId'] == 'security-admin-console') {
                    $adminConsoleClient = $c;
                    break;
                }
            }

            if (!$adminConsoleClient) {
                Log::warning("security-admin-console client not found in realm $realmId");
                return false;
            }

            $clientInternalId = $adminConsoleClient['id'];

            // Update client with simplified settings
            $this->client->put("/admin/realms/$realmId/clients/$clientInternalId", [
                'headers' => [
                    'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'clientId' => 'security-admin-console',
                    'name' => 'security-admin-console',
                    'enabled' => true,
                    'publicClient' => true,
                    'protocol' => 'openid-connect',
                    'standardFlowEnabled' => true,
                    'implicitFlowEnabled' => true,
                    'directAccessGrantsEnabled' => true,
                    'bearerOnly' => false,
                    'consentRequired' => false,
                    'fullScopeAllowed' => true,
                    'frontchannelLogout' => true,
                    'attributes' => [
                        'post.logout.redirect.uris' => '+',
                        'oauth2.device.authorization.grant.enabled' => 'false',
                        'oidc.ciba.grant.enabled' => 'false',
                        'backchannel.logout.session.required' => 'true',
                        'backchannel.logout.revoke.offline.tokens' => 'false',
                    ],
                    'redirectUris' => [
                        "{$this->baseUrl}/admin/$realmId/console/*",
                    ],
                    'webOrigins' => ['+'],
                    'baseUrl' => "{$this->baseUrl}/admin/$realmId/console/",
                ],
            ]);

            Log::info("Fixed admin console client for realm $realmId");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to fix admin console client for realm $realmId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Assign realm-admin role to a user
     */
    public function assignRealmAdminRole($realmId, $userEmail)
    {
        try {
            $token = $this->getAdminToken();

            // Get user by email
            $usersResponse = $this->client->get("/admin/realms/$realmId/users", [
                'headers' => ['Authorization' => "Bearer $token"],
                'query' => ['email' => $userEmail],
            ]);

            $users = json_decode($usersResponse->getBody(), true);

            if (empty($users)) {
                Log::warning("User $userEmail not found in realm $realmId");
                return false;
            }

            $userId = $users[0]['id'];

            // Get realm-management client
            $clientsResponse = $this->client->get("/admin/realms/$realmId/clients", [
                'headers' => ['Authorization' => "Bearer $token"],
                'query' => ['clientId' => 'realm-management'],
            ]);

            $clients = json_decode($clientsResponse->getBody(), true);

            if (empty($clients)) {
                Log::warning("realm-management client not found in realm $realmId");
                return false;
            }

            $realmMgmtClientId = $clients[0]['id'];

            // Get realm-admin role
            $rolesResponse = $this->client->get("/admin/realms/$realmId/clients/$realmMgmtClientId/roles", [
                'headers' => ['Authorization' => "Bearer $token"],
            ]);

            $roles = json_decode($rolesResponse->getBody(), true);
            $adminRole = null;

            foreach ($roles as $role) {
                if ($role['name'] == 'realm-admin') {
                    $adminRole = $role;
                    break;
                }
            }

            if (!$adminRole) {
                Log::warning("realm-admin role not found in realm $realmId");
                return false;
            }

            // Assign role to user
            $this->client->post("/admin/realms/$realmId/users/$userId/role-mappings/clients/$realmMgmtClientId", [
                'headers' => [
                    'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json',
                ],
                'json' => [$adminRole],
            ]);

            Log::info("Assigned realm-admin role to user $userEmail in realm $realmId");
            return true;

        } catch (\Exception $e) {
            // Role might already be assigned
            if (strpos($e->getMessage(), '409') !== false) {
                Log::info("realm-admin role already assigned to user $userEmail");
                return true;
            }

            Log::error("Failed to assign realm-admin role: " . $e->getMessage());
            return false;
        }
    }
}
