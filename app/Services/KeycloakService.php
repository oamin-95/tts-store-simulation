<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class KeycloakService
{
    protected $client;
    protected $baseUrl;
    protected $serviceClientId;
    protected $serviceClientSecret;
    protected $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('services.keycloak.url', 'http://localhost:8090');
        $this->serviceClientId = config('services.keycloak.service_account.client_id');
        $this->serviceClientSecret = config('services.keycloak.service_account.client_secret');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'verify' => false,
            'timeout' => 30,
        ]);
    }

    /**
     * Get Service Account access token using client_credentials grant
     *
     * SECURITY: Uses OAuth 2.0 Client Credentials - no username/password
     *
     * @param bool $forceRefresh Force getting a new token (for session refresh after realm creation)
     * @return string
     */
    protected function getAdminToken($forceRefresh = false)
    {
        // Force refresh if requested (e.g., after creating a realm)
        if ($forceRefresh) {
            $this->accessToken = null;
        }

        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            $response = $this->client->post('/realms/master/protocol/openid-connect/token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->serviceClientId,
                    'client_secret' => $this->serviceClientSecret,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $this->accessToken = $data['access_token'];

            Log::info('Keycloak service account token obtained', [
                'client_id' => $this->serviceClientId,
            ]);

            return $this->accessToken;
        } catch (\Exception $e) {
            Log::error('Failed to get Keycloak service account token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Refresh the access token
     *
     * CRITICAL: This is needed after creating a new realm to avoid 403 Forbidden errors
     * Keycloak's session caches permissions for realms that existed when the token was issued
     *
     * @return string
     */
    protected function refreshToken()
    {
        return $this->getAdminToken(true);
    }

    /**
     * Create a new realm for a tenant
     *
     * @param int $tenantId
     * @param string $tenantName
     * @return string Realm ID
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

            // CRITICAL: Refresh token after creating realm to avoid 403 Forbidden
            $this->refreshToken();
            Log::info("Token refreshed after creating realm {$realmId}");

            return $realmId;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // If realm already exists (409 Conflict), just return the realm ID
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 409) {
                Log::warning("Realm {$realmId} already exists, using existing realm");
                // Still refresh token to ensure we have permissions
                $this->refreshToken();
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
            // CRITICAL: Refresh token to ensure permissions include this realm
            // The token issued before realm creation doesn't have permissions for the new realm
            $token = $this->refreshToken();

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
            // IMPORTANT: Only send id and name, not the full role object
            $this->client->post("/admin/realms/$realmId/users/$userId/role-mappings/clients/$realmMgmtClientId", [
                'headers' => [
                    'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json',
                ],
                'json' => [[
                    'id' => $adminRole['id'],
                    'name' => $adminRole['name'],
                ]],
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

    /**
     * Create a product client in tenant's realm
     *
     * @param string $realmId
     * @param string $productSlug (e.g., 'training-platform', 'services-platform')
     * @param string $productUrl Base URL (e.g., 'http://localhost:5000')
     * @param string|null $domain Tenant-specific domain (e.g., 'training-user-123.localhost')
     * @return array ['client_id' => string, 'client_secret' => string, 'client_uuid' => string]
     */
    public function createProductClient($realmId, $productSlug, $productUrl, $domain = null)
    {
        $token = $this->getAdminToken();
        $clientId = $productSlug;

        // Extract port from productUrl
        $parsedUrl = parse_url($productUrl);
        $port = $parsedUrl['port'] ?? 80;

        // Use tenant-specific domain if provided, otherwise use base URL
        $baseUrl = $domain ? "http://{$domain}:{$port}" : $productUrl;

        $redirectUris = [
            $baseUrl . '/*',
            $baseUrl . '/auth/callback',
            $baseUrl . '/auth/keycloak/callback',
        ];

        // LOG: URL construction details
        Log::info("KeycloakService::createProductClient - URL construction", [
            'realm_id' => $realmId,
            'product_slug' => $productSlug,
            'product_url' => $productUrl,
            'domain_param' => $domain,
            'domain_is_null' => is_null($domain),
            'domain_is_empty' => empty($domain),
            'parsed_port' => $port,
            'constructed_base_url' => $baseUrl,
            'redirect_uris' => $redirectUris,
        ]);

        try {
            $response = $this->client->post("/admin/realms/{$realmId}/clients", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'clientId' => $clientId,
                    'name' => ucfirst(str_replace('-', ' ', $productSlug)),
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
                    'rootUrl' => $baseUrl,
                    'baseUrl' => $baseUrl,
                    'adminUrl' => $baseUrl,
                    'attributes' => [
                        'access.token.lifespan' => '3600',
                        'client.session.idle.timeout' => '3600',
                        'client.session.max.lifespan' => '86400',
                        'post.logout.redirect.uris' => $baseUrl . '/*',
                    ],
                ],
            ]);

            // Get client UUID
            $clientUuid = $this->getClientUuid($realmId, $clientId);

            // Generate client secret
            $secret = $this->regenerateClientSecret($realmId, $clientUuid);

            // Refresh token to get updated permissions after creating client
            $this->refreshToken();

            // Grant realm-management permissions to service account
            $this->grantProductClientPermissions($realmId, $clientUuid);

            // Add group mapper to include groups in token
            $this->addGroupMapperToClient($realmId, $clientUuid);

            Log::info("Created product client {$clientId} in realm {$realmId}");

            return [
                'client_id' => $clientId,
                'client_secret' => $secret,
                'client_uuid' => $clientUuid,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to create product client {$productSlug} in realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Grant realm-management permissions to Product Client Service Account
     *
     * This allows the Product to manage its own groups, roles, and users
     *
     * @param string $realmId
     * @param string $clientUuid
     * @return bool
     */
    protected function grantProductClientPermissions($realmId, $clientUuid)
    {
        try {
            $token = $this->refreshToken();

            // Get Service Account User for this client
            $saResponse = $this->client->get("/admin/realms/{$realmId}/clients/{$clientUuid}/service-account-user", [
                'headers' => ['Authorization' => "Bearer $token"],
            ]);

            $serviceAccountUser = json_decode($saResponse->getBody(), true);

            if (!isset($serviceAccountUser['id'])) {
                Log::error("Failed to get service account user for client {$clientUuid}");
                return false;
            }

            $serviceAccountUserId = $serviceAccountUser['id'];

            // Get realm-management client
            $clientsResponse = $this->client->get("/admin/realms/{$realmId}/clients", [
                'headers' => ['Authorization' => "Bearer $token"],
                'query' => ['clientId' => 'realm-management'],
            ]);

            $clients = json_decode($clientsResponse->getBody(), true);

            if (empty($clients)) {
                Log::error("realm-management client not found in realm {$realmId}");
                return false;
            }

            $realmMgmtClientId = $clients[0]['id'];

            // Get available roles from realm-management
            $rolesResponse = $this->client->get("/admin/realms/{$realmId}/clients/{$realmMgmtClientId}/roles", [
                'headers' => ['Authorization' => "Bearer $token"],
            ]);

            $allRoles = json_decode($rolesResponse->getBody(), true);

            // Roles needed for product to manage its groups and roles
            $requiredRoles = [
                'manage-users',
                'view-users',
                'query-users',
                'query-groups',
                'manage-realm',
                'view-realm',
            ];

            $rolesToAssign = [];
            foreach ($allRoles as $role) {
                if (in_array($role['name'], $requiredRoles)) {
                    $rolesToAssign[] = [
                        'id' => $role['id'],
                        'name' => $role['name'],
                    ];
                }
            }

            if (empty($rolesToAssign)) {
                Log::warning("No required roles found in realm-management for realm {$realmId}");
                return false;
            }

            // Assign roles to service account user
            $this->client->post("/admin/realms/{$realmId}/users/{$serviceAccountUserId}/role-mappings/clients/{$realmMgmtClientId}", [
                'headers' => [
                    'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json',
                ],
                'json' => $rolesToAssign,
            ]);

            Log::info("Granted realm-management permissions to Product Client in realm {$realmId}", [
                'client_uuid' => $clientUuid,
                'roles_count' => count($rolesToAssign),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to grant permissions to Product Client in realm {$realmId}: " . $e->getMessage());
            // Don't throw - this shouldn't fail the whole client creation
            return false;
        }
    }

    /**
     * Add Group Mapper to Product Client
     *
     * This ensures user groups are included in the access token
     *
     * @param string $realmId
     * @param string $clientUuid
     * @return bool
     */
    protected function addGroupMapperToClient($realmId, $clientUuid)
    {
        try {
            $token = $this->refreshToken();

            // Create protocol mapper for groups
            $this->client->post("/admin/realms/{$realmId}/clients/{$clientUuid}/protocol-mappers/models", [
                'headers' => [
                    'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'name' => 'groups',
                    'protocol' => 'openid-connect',
                    'protocolMapper' => 'oidc-group-membership-mapper',
                    'consentRequired' => false,
                    'config' => [
                        'full.path' => 'false',
                        'id.token.claim' => 'true',
                        'access.token.claim' => 'true',
                        'claim.name' => 'groups',
                        'userinfo.token.claim' => 'true',
                    ],
                ],
            ]);

            Log::info("Added group mapper to Product Client in realm {$realmId}");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to add group mapper to Product Client in realm {$realmId}: " . $e->getMessage());
            // Don't throw - this shouldn't fail the whole client creation
            return false;
        }
    }

    /**
     * Get all groups in a realm
     *
     * @param string $realmId
     * @return array
     */
    public function getGroups($realmId)
    {
        $token = $this->getAdminToken();

        try {
            $response = $this->client->get("/admin/realms/{$realmId}/groups", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            $groups = json_decode($response->getBody()->getContents(), true);

            // Enrich each group with member count
            foreach ($groups as &$group) {
                try {
                    $membersResponse = $this->client->get("/admin/realms/{$realmId}/groups/{$group['id']}/members", [
                        'headers' => ['Authorization' => 'Bearer ' . $token],
                    ]);
                    $members = json_decode($membersResponse->getBody()->getContents(), true);
                    $group['membersCount'] = count($members);
                } catch (\Exception $e) {
                    Log::warning("Failed to get member count for group {$group['id']}: " . $e->getMessage());
                    $group['membersCount'] = 0;
                }
            }

            return $groups;
        } catch (\Exception $e) {
            Log::error("Failed to get groups from realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a group in a realm
     *
     * @param string $realmId
     * @param string $groupName
     * @param array $attributes
     * @return bool
     */
    public function createGroup($realmId, $groupName, array $attributes = [])
    {
        $token = $this->getAdminToken();

        try {
            $groupData = ['name' => $groupName];

            if (!empty($attributes)) {
                $groupData['attributes'] = $attributes;
            }

            $response = $this->client->post("/admin/realms/{$realmId}/groups", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $groupData,
            ]);

            Log::info("Created group {$groupName} in realm {$realmId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create group {$groupName} in realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get a specific group
     *
     * @param string $realmId
     * @param string $groupId
     * @return array
     */
    public function getGroup($realmId, $groupId)
    {
        $token = $this->getAdminToken();

        try {
            $response = $this->client->get("/admin/realms/{$realmId}/groups/{$groupId}", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error("Failed to get group {$groupId} from realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update a group
     *
     * @param string $realmId
     * @param string $groupId
     * @param string $groupName
     * @param array $attributes
     * @return bool
     */
    public function updateGroup($realmId, $groupId, $groupName, array $attributes = [])
    {
        $token = $this->getAdminToken();

        try {
            $groupData = ['name' => $groupName];

            if (!empty($attributes)) {
                $groupData['attributes'] = $attributes;
            }

            $this->client->put("/admin/realms/{$realmId}/groups/{$groupId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $groupData,
            ]);

            Log::info("Updated group {$groupId} in realm {$realmId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update group {$groupId} in realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a group
     *
     * @param string $realmId
     * @param string $groupId
     * @return bool
     */
    public function deleteGroup($realmId, $groupId)
    {
        $token = $this->getAdminToken();

        try {
            $this->client->delete("/admin/realms/{$realmId}/groups/{$groupId}", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            Log::info("Deleted group {$groupId} from realm {$realmId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete group {$groupId} from realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get group members
     *
     * @param string $realmId
     * @param string $groupId
     * @return array
     */
    public function getGroupMembers($realmId, $groupId)
    {
        $token = $this->getAdminToken();

        try {
            $response = $this->client->get("/admin/realms/{$realmId}/groups/{$groupId}/members", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error("Failed to get members of group {$groupId} from realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user by email
     *
     * @param string $realmId
     * @param string $email
     * @return array|null
     */
    public function getUserByEmail($realmId, $email)
    {
        $token = $this->getAdminToken();

        try {
            $response = $this->client->get("/admin/realms/{$realmId}/users", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'query' => ['email' => $email],
            ]);

            $users = json_decode($response->getBody()->getContents(), true);
            return !empty($users) ? $users[0] : null;
        } catch (\Exception $e) {
            Log::error("Failed to get user by email {$email} from realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add user to group
     *
     * @param string $realmId
     * @param string $userId
     * @param string $groupId
     * @return bool
     */
    public function addUserToGroup($realmId, $userId, $groupId)
    {
        $token = $this->getAdminToken();

        try {
            $response = $this->client->put("/admin/realms/{$realmId}/users/{$userId}/groups/{$groupId}", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            Log::info("Added user {$userId} to group {$groupId} in realm {$realmId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to add user to group in realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user's groups
     *
     * @param string $realmId
     * @param string $userId
     * @return array
     */
    public function getUserGroups($realmId, $userId)
    {
        $token = $this->getAdminToken();

        try {
            $response = $this->client->get("/admin/realms/{$realmId}/users/{$userId}/groups", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error("Failed to get user groups from realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all users in a realm
     *
     * @param string $realmId
     * @param int $first Pagination: first result
     * @param int $max Pagination: max results
     * @return array
     */
    public function getUsers($realmId, $first = 0, $max = 100)
    {
        $token = $this->getAdminToken();

        try {
            $response = $this->client->get("/admin/realms/{$realmId}/users", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'query' => [
                    'first' => $first,
                    'max' => $max,
                ],
            ]);

            $users = json_decode($response->getBody()->getContents(), true);

            // Enrich each user with their groups
            foreach ($users as &$user) {
                try {
                    $groupsResponse = $this->client->get("/admin/realms/{$realmId}/users/{$user['id']}/groups", [
                        'headers' => ['Authorization' => 'Bearer ' . $token],
                    ]);
                    $user['groups'] = json_decode($groupsResponse->getBody()->getContents(), true);
                } catch (\Exception $e) {
                    Log::warning("Failed to get groups for user {$user['id']}: " . $e->getMessage());
                    $user['groups'] = [];
                }
            }

            return $users;
        } catch (\Exception $e) {
            Log::error("Failed to get users from realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user by ID
     *
     * @param string $realmId
     * @param string $userId
     * @return array|null
     */
    public function getUser($realmId, $userId)
    {
        $token = $this->getAdminToken();

        try {
            $response = $this->client->get("/admin/realms/{$realmId}/users/{$userId}", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error("Failed to get user {$userId} from realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update user in realm
     *
     * @param string $realmId
     * @param string $userId
     * @param array $userData
     * @return bool
     */
    public function updateUser($realmId, $userId, array $userData)
    {
        $token = $this->getAdminToken();

        try {
            $updateData = [
                'username' => $userData['username'] ?? null,
                'email' => $userData['email'] ?? null,
                'firstName' => $userData['first_name'] ?? null,
                'lastName' => $userData['last_name'] ?? null,
                'enabled' => $userData['enabled'] ?? null,
                'emailVerified' => $userData['email_verified'] ?? null,
            ];

            // Remove null values
            $updateData = array_filter($updateData, fn($value) => $value !== null);

            $this->client->put("/admin/realms/{$realmId}/users/{$userId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $updateData,
            ]);

            Log::info("Updated user {$userId} in realm {$realmId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update user {$userId} in realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete user from realm
     *
     * @param string $realmId
     * @param string $userId
     * @return bool
     */
    public function deleteUser($realmId, $userId)
    {
        $token = $this->getAdminToken();

        try {
            $this->client->delete("/admin/realms/{$realmId}/users/{$userId}", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            Log::info("Deleted user {$userId} from realm {$realmId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete user {$userId} from realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reset user password
     *
     * @param string $realmId
     * @param string $userId
     * @param string $newPassword
     * @param bool $temporary
     * @return bool
     */
    public function resetUserPassword($realmId, $userId, $newPassword, $temporary = false)
    {
        $token = $this->getAdminToken();

        try {
            $this->client->put("/admin/realms/{$realmId}/users/{$userId}/reset-password", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'type' => 'password',
                    'value' => $newPassword,
                    'temporary' => $temporary,
                ],
            ]);

            Log::info("Reset password for user {$userId} in realm {$realmId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to reset password for user {$userId} in realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Assign user to group
     *
     * @param string $realmId
     * @param string $userId
     * @param string $groupId
     * @return bool
     */
    public function assignUserToGroup($realmId, $userId, $groupId)
    {
        $token = $this->getAdminToken();

        try {
            $this->client->put("/admin/realms/{$realmId}/users/{$userId}/groups/{$groupId}", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            Log::info("Assigned user {$userId} to group {$groupId} in realm {$realmId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to assign user {$userId} to group {$groupId} in realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Remove user from group
     *
     * @param string $realmId
     * @param string $userId
     * @param string $groupId
     * @return bool
     */
    public function removeUserFromGroup($realmId, $userId, $groupId)
    {
        $token = $this->getAdminToken();

        try {
            $this->client->delete("/admin/realms/{$realmId}/users/{$userId}/groups/{$groupId}", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            Log::info("Removed user {$userId} from group {$groupId} in realm {$realmId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to remove user {$userId} from group {$groupId} in realm {$realmId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update Product Client URLs with correct domain
     *
     * @param string $realmId Keycloak realm ID
     * @param string $clientUuid Client UUID
     * @param string $domain Tenant-specific domain (e.g., training-user-123-456.localhost)
     * @param int $port Port number
     */
    public function updateClientUrls($realmId, $clientUuid, $domain, $port)
    {
        $token = $this->getAdminToken();
        $baseUrl = "http://{$domain}:{$port}";

        $redirectUris = [
            $baseUrl . '/*',
            $baseUrl . '/auth/callback',
            $baseUrl . '/auth/keycloak/callback',
        ];

        try {
            $this->client->put("/admin/realms/{$realmId}/clients/{$clientUuid}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'redirectUris' => $redirectUris,
                    'webOrigins' => ['*'],
                    'rootUrl' => $baseUrl,
                    'baseUrl' => $baseUrl,
                    'adminUrl' => $baseUrl,
                    'attributes' => [
                        'post.logout.redirect.uris' => $baseUrl . '/*',
                    ],
                ],
            ]);

            Log::info("Updated client URLs for {$clientUuid} in realm {$realmId}", [
                'base_url' => $baseUrl,
                'redirect_uris' => $redirectUris,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update client URLs: " . $e->getMessage());
            throw $e;
        }
    }
}
