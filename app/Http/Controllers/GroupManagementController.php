<?php

namespace App\Http\Controllers;

use App\Services\KeycloakService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GroupManagementController extends Controller
{
    protected $keycloak;

    public function __construct(KeycloakService $keycloak)
    {
        $this->keycloak = $keycloak;
    }

    /**
     * Display list of groups in current user's realm
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return back()->with('error', 'لا يوجد Keycloak Realm مرتبط بحسابك');
        }

        try {
            $groups = $this->keycloak->getGroups($user->keycloak_realm_id);

            return view('groups.index', [
                'groups' => $groups,
                'realmId' => $user->keycloak_realm_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch groups: ' . $e->getMessage());
            return back()->with('error', 'فشل في تحميل المجموعات: ' . $e->getMessage());
        }
    }

    /**
     * Show create group form
     */
    public function create()
    {
        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('groups.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        return view('groups.create', [
            'realmId' => $user->keycloak_realm_id,
        ]);
    }

    /**
     * Store new group
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('groups.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            $attributes = [];
            if ($request->description) {
                $attributes['description'] = [$request->description];
            }

            $this->keycloak->createGroup($user->keycloak_realm_id, $request->name, $attributes);

            return redirect()->route('groups.index')->with('success', 'تم إنشاء المجموعة بنجاح');
        } catch (\Exception $e) {
            Log::error('Failed to create group: ' . $e->getMessage());
            return back()->with('error', 'فشل في إنشاء المجموعة: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show edit group form
     */
    public function edit($groupId)
    {
        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('groups.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            $group = $this->keycloak->getGroup($user->keycloak_realm_id, $groupId);

            return view('groups.edit', [
                'group' => $group,
                'realmId' => $user->keycloak_realm_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load edit group form: ' . $e->getMessage());
            return redirect()->route('groups.index')->with('error', 'فشل في تحميل بيانات المجموعة');
        }
    }

    /**
     * Update group
     */
    public function update(Request $request, $groupId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('groups.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            $attributes = [];
            if ($request->description) {
                $attributes['description'] = [$request->description];
            }

            $this->keycloak->updateGroup($user->keycloak_realm_id, $groupId, $request->name, $attributes);

            return redirect()->route('groups.index')->with('success', 'تم تحديث المجموعة بنجاح');
        } catch (\Exception $e) {
            Log::error('Failed to update group: ' . $e->getMessage());
            return back()->with('error', 'فشل في تحديث المجموعة: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Delete group
     */
    public function destroy($groupId)
    {
        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('groups.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            $this->keycloak->deleteGroup($user->keycloak_realm_id, $groupId);
            return redirect()->route('groups.index')->with('success', 'تم حذف المجموعة بنجاح');
        } catch (\Exception $e) {
            Log::error('Failed to delete group: ' . $e->getMessage());
            return back()->with('error', 'فشل في حذف المجموعة: ' . $e->getMessage());
        }
    }

    /**
     * Show group members
     */
    public function members($groupId)
    {
        $user = Auth::user();

        if (!$user->keycloak_realm_id) {
            return redirect()->route('groups.index')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            $group = $this->keycloak->getGroup($user->keycloak_realm_id, $groupId);
            $members = $this->keycloak->getGroupMembers($user->keycloak_realm_id, $groupId);

            return view('groups.members', [
                'group' => $group,
                'members' => $members,
                'realmId' => $user->keycloak_realm_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch group members: ' . $e->getMessage());
            return redirect()->route('groups.index')->with('error', 'فشل في تحميل أعضاء المجموعة');
        }
    }
}
