<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Customers\StoreCustomerNoteRequest;
use App\Http\Requests\Admin\Customers\UpdateCustomerNoteRequest;
use App\Models\CustomerNote;
use App\Models\User;
use App\Services\CustomerManagementService;
use Illuminate\Http\RedirectResponse;

class AdminCustomerNoteController extends Controller
{
    public function __construct(
        protected CustomerManagementService $customerManagementService,
    ) {}

    public function store(StoreCustomerNoteRequest $request, User $customer): RedirectResponse
    {
        $this->authorize('manageNotes', $customer);

        $note = $this->customerManagementService->storeNote(
            $customer,
            $request->user(),
            (string) $request->validated('note'),
        );

        if (!$note) {
            return back()->with('error', 'Customer notes are unavailable until the latest database migrations are run.');
        }

        return back()->with('success', 'Customer note added.');
    }

    public function update(UpdateCustomerNoteRequest $request, User $customer, CustomerNote $note): RedirectResponse
    {
        $this->authorize('manageNotes', $customer);
        abort_unless((int) $note->user_id === (int) $customer->id, 404);

        $updated = $this->customerManagementService->updateNote(
            $note,
            (string) $request->validated('note'),
            $request->user(),
        );

        if (!$updated) {
            return back()->with('error', 'Customer notes are unavailable until the latest database migrations are run.');
        }

        return back()->with('success', 'Customer note updated.');
    }

    public function destroy(User $customer, CustomerNote $note): RedirectResponse
    {
        $this->authorize('manageNotes', $customer);
        abort_unless((int) $note->user_id === (int) $customer->id, 404);

        if (!$this->customerManagementService->deleteNote($note, request()->user())) {
            return back()->with('error', 'Customer notes are unavailable until the latest database migrations are run.');
        }

        return back()->with('success', 'Customer note deleted.');
    }
}
