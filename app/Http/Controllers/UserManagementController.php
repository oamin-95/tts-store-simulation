<?php

namespace App\Http\Controllers;

use App\Services\KeycloakService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserManagementController extends Controller
{
    protected $keycloak;

    public function __construct(KeycloakService $keycloak)
    {
        $this->keycloak = $keycloak;
    }

    /**
     * Display list of users in current user's realm
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return back()->with('error', 'لا يوجد Keycloak Realm مرتبط بحسابك');
        }

        try {
            $users = $this->keycloak->getUsers($user->keycloak_realm_id);

            return view('users.index', [
                'users' => $users,
                'realmId' => $user->keycloak_realm_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch users: ' . $e->getMessage());
            return back()->with('error', 'فشل في تحميل المستخدمين: ' . $e->getMessage());
        }
    }

    /**
     * Show create user form
     */
    public function create()
    {
        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('users.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            $groups = $this->keycloak->getGroups($user->keycloak_realm_id);

            return view('users.create', [
                'groups' => $groups,
                'realmId' => $user->keycloak_realm_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load create user form: ' . $e->getMessage());
            return redirect()->route('users.index')->with('error', 'فشل في تحميل النموذج');
        }
    }

    /**
     * Store new user
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'password' => 'required|string|min:8',
            'enabled' => 'boolean',
            'email_verified' => 'boolean',
            'temporary_password' => 'boolean',
            'groups' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('users.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            // Create user
            $this->keycloak->createUser($user->keycloak_realm_id, [
                'username' => $request->username,
                'email' => $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'password' => $request->password,
                'enabled' => $request->boolean('enabled', true),
                'email_verified' => $request->boolean('email_verified', false),
                'temporary_password' => $request->boolean('temporary_password', true),
            ]);

            // Assign to groups if specified
            if ($request->has('groups') && is_array($request->groups)) {
                $users = $this->keycloak->getUsers($user->keycloak_realm_id);
                $newUser = collect($users)->firstWhere('username', $request->username);

                if ($newUser) {
                    foreach ($request->groups as $groupId) {
                        $this->keycloak->assignUserToGroup($user->keycloak_realm_id, $newUser['id'], $groupId);
                    }
                }
            }

            return redirect()->route('users.index')->with('success', 'تم إنشاء المستخدم بنجاح');
        } catch (\Exception $e) {
            Log::error('Failed to create user: ' . $e->getMessage());
            return back()->with('error', 'فشل في إنشاء المستخدم: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show edit user form
     */
    public function edit($userId)
    {
        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('users.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            $kcUser = $this->keycloak->getUser($user->keycloak_realm_id, $userId);
            $groups = $this->keycloak->getGroups($user->keycloak_realm_id);
            $userGroups = $this->keycloak->getUserGroups($user->keycloak_realm_id, $userId);

            return view('users.edit', [
                'user' => $kcUser,
                'groups' => $groups,
                'userGroups' => $userGroups,
                'realmId' => $user->keycloak_realm_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load edit user form: ' . $e->getMessage());
            return redirect()->route('users.index')->with('error', 'فشل في تحميل بيانات المستخدم');
        }
    }

    /**
     * Update user
     */
    public function update(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'enabled' => 'boolean',
            'email_verified' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('users.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            $this->keycloak->updateUser($user->keycloak_realm_id, $userId, [
                'username' => $request->username,
                'email' => $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'enabled' => $request->boolean('enabled', true),
                'email_verified' => $request->boolean('email_verified', false),
            ]);

            return redirect()->route('users.index')->with('success', 'تم تحديث المستخدم بنجاح');
        } catch (\Exception $e) {
            Log::error('Failed to update user: ' . $e->getMessage());
            return back()->with('error', 'فشل في تحديث المستخدم: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Delete user
     */
    public function destroy($userId)
    {
        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('users.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            $this->keycloak->deleteUser($user->keycloak_realm_id, $userId);
            return redirect()->route('users.index')->with('success', 'تم حذف المستخدم بنجاح');
        } catch (\Exception $e) {
            Log::error('Failed to delete user: ' . $e->getMessage());
            return back()->with('error', 'فشل في حذف المستخدم: ' . $e->getMessage());
        }
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8',
            'temporary' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('users.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            $this->keycloak->resetUserPassword(
                $user->keycloak_realm_id,
                $userId,
                $request->password,
                $request->boolean('temporary', true)
            );

            return back()->with('success', 'تم إعادة تعيين كلمة المرور بنجاح');
        } catch (\Exception $e) {
            Log::error('Failed to reset password: ' . $e->getMessage());
            return back()->with('error', 'فشل في إعادة تعيين كلمة المرور: ' . $e->getMessage());
        }
    }

    /**
     * Assign user to group
     */
    public function assignGroup(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('users.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            $this->keycloak->assignUserToGroup($user->keycloak_realm_id, $userId, $request->group_id);
            return back()->with('success', 'تم إضافة المستخدم إلى المجموعة بنجاح');
        } catch (\Exception $e) {
            Log::error('Failed to assign user to group: ' . $e->getMessage());
            return back()->with('error', 'فشل في إضافة المستخدم إلى المجموعة: ' . $e->getMessage());
        }
    }

    /**
     * Remove user from group
     */
    public function removeGroup(Request $request, $userId, $groupId)
    {
        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('users.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            $this->keycloak->removeUserFromGroup($user->keycloak_realm_id, $userId, $groupId);
            return back()->with('success', 'تم إزالة المستخدم من المجموعة بنجاح');
        } catch (\Exception $e) {
            Log::error('Failed to remove user from group: ' . $e->getMessage());
            return back()->with('error', 'فشل في إزالة المستخدم من المجموعة: ' . $e->getMessage());
        }
    }
}
