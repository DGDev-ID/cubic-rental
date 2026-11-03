<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberPackage;
use App\Models\Employee;
use App\Services\MemberService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function __construct(private MemberService $memberService) {}

    public function index(Request $request): Response
    {
        $query = Member::with(['deposits'])
            ->withCount('deposits')
            ->withSum('deposits', 'amount');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('member_number', 'ilike', "%{$request->search}%")
                  ->orWhere('phone', 'ilike', "%{$request->search}%");
            });
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $members = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $stats = [
            'total'     => Member::count(),
            'active'    => Member::where('status', 'active')->count(),
            'exhausted' => Member::where('status', 'exhausted')->count(),
            'total_revenue' => \App\Models\MemberDeposit::sum('amount'),
        ];

        return Inertia::render('Members/Index', [
            'members'   => $members,
            'packages'  => MemberPackage::where('is_active', true)->orderBy('price')->get(),
            'employees' => Employee::orderBy('name')->get(),
            'stats'     => $stats,
            'filters'   => $request->only(['search', 'status']),
        ]);
    }

    public function show(Member $member): Response
    {
        $member->load(['deposits.package', 'deposits.employee']);

        $rentals = $member->rentals()
            ->with(['console', 'fnbItems.fnbItem'])
            ->orderByDesc('started_at')
            ->get();

        return Inertia::render('Members/Show', [
            'member'    => $member,
            'deposits'  => $member->deposits,
            'rentals'   => $rentals,
            'packages'  => MemberPackage::where('is_active', true)
                            ->where('console_type', $member->console_type)
                            ->orderBy('price')->get(),
            'employees' => Employee::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:100',
            'phone'             => 'nullable|string|max:20',
            'member_package_id' => 'required|exists:member_packages,id',
            'employee_id'       => 'required|exists:employees,id',
            'payment_method'    => 'required|in:cash,qris',
            'notes'             => 'nullable|string|max:255',
        ]);

        $member = $this->memberService->register($data);

        return redirect()->route('members.show', $member)
            ->with('success', "Member {$member->member_number} berhasil didaftarkan!");
    }

    public function deposit(Request $request, Member $member): RedirectResponse
    {
        $data = $request->validate([
            'member_package_id' => 'required|exists:member_packages,id',
            'employee_id'       => 'required|exists:employees,id',
            'payment_method'    => 'required|in:cash,qris',
            'notes'             => 'nullable|string|max:255',
        ]);

        try {
            $this->memberService->deposit($member, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['member_package_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Deposit berhasil ditambahkan!');
    }
}

