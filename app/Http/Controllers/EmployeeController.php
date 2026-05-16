<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Http\Requests\EmployeeRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Employees/Index', [
            'employees' => Employee::withTrashed()->orderBy('name')->get(),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        Employee::create($request->validated());
        return back()->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());
        return back()->with('success', 'Karyawan berhasil diperbarui.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();
        return back()->with('success', 'Karyawan berhasil dihapus.');
    }

    public function restore(int $id): RedirectResponse
    {
        Employee::withTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'Karyawan berhasil dipulihkan.');
    }
}
