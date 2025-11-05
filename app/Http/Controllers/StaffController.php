<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\EmployeeWarehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function index()
    {
        $staff = EmployeeWarehouse::with([
            'employee:id,name,email',
            'employee.roles:id,name',        // ✅ load roles assigned to user
            'warehouse:id,name',
        ])
            ->latest()
            ->get()
            ->map(function ($staff) {
                return [
                    'id' => $staff->id,
                    'employee' => [
                        'id' => $staff->employee->id,
                        'name' => $staff->employee->name,
                        'email' => $staff->employee->email,
                        'role' => $staff->employee->roles->pluck('name')->first(), // ✅ Extract role name
                    ],
                    'warehouse' => [
                        'id' => $staff->warehouse->id,
                        'name' => $staff->warehouse->name,
                    ],
                ];
            });

        return Inertia::render('Staff/Index', [
            'staff' => $staff,
        ]);
    }


    public function create()
    {
        return Inertia::render('Staff/Form', [
            'warehouses' => Warehouse::select('id', 'name')->get(),
            'roles'       => Role::select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'role'         => 'required|string|exists:roles,name',
        ]);

        // Assign role to user
        $user = User::findOrFail($validated['employee_id']);
        $user->syncRoles([$validated['role']]); // assign only one role

        // Assign to warehouse
        EmployeeWarehouse::firstOrCreate([
            'employee_id'   => $validated['employee_id'],
            'warehouse_id'  => $validated['warehouse_id'],
        ]);

        return redirect()->route('admin.staff.index')->with('success', 'Staff assigned successfully.');
    }

    public function edit(EmployeeWarehouse $staff)
    {
        return Inertia::render('Staff/Form', [
            'staff'      => [
                'id'           => $staff->id,
                'employee_id'  => $staff->employee_id,
                'warehouse_id' => $staff->warehouse_id,
                'role'         => $staff->employee->getRoleNames()->first() ?? null,
            ],
            'warehouses' => Warehouse::select('id', 'name')->get(),
            'roles'      => Role::select('id', 'name')->get(),
        ]);
    }

    public function update(Request $request, EmployeeWarehouse $staff)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'role'         => 'required|string|exists:roles,name',
        ]);

        $staff->update([
            'warehouse_id' => $validated['warehouse_id'],
        ]);

        // Sync role
        $staff->employee->syncRoles([$validated['role']]);

        return redirect()->route('admin.staff.index')->with('success', 'Staff updated successfully.');
    }

    public function destroy(EmployeeWarehouse $staff)
    {
        // Remove assigned role from user
        $staff->employee->roles()->detach();

        // Remove warehouse assignment
        $staff->delete();

        return back()->with('success', 'Staff removed successfully.');
    }

    /**
     * API endpoint for user search
     */
    public function searchUser(Request $request)
    {
        $users = User::query()
            ->where('name', 'LIKE', '%' . $request->input('search') . '%')
            ->orWhere('email', 'LIKE', '%' . $request->input('search') . '%')
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json($users);
    }
}
