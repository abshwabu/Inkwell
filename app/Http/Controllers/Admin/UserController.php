<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a list of all registered users with search and filtering.
     */
    public function index(Request $request): Response
    {
        $query = User::withCount(['posts', 'comments'])->latest();

        if ($request->filled('q')) {
            $search = '%' . trim($request->q) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('username', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            if ($request->status === 'banned') {
                $query->where('is_banned', true);
            } elseif ($request->status === 'active') {
                $query->where('is_banned', false);
            }
        }

        $users = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['q', 'role', 'status']),
            'roles' => [
                UserRole::USER->value,
                UserRole::MODERATOR->value,
                UserRole::ADMIN->value,
            ],
        ]);
    }

    /**
     * View a specific user's activity details.
     */
    public function show(User $user): Response
    {
        $user->loadCount(['posts', 'comments', 'followers', 'following']);

        $recentPosts = $user->posts()->latest()->take(5)->get();
        $recentComments = $user->comments()->with('post')->latest()->take(5)->get();

        $reportsAgainst = Report::where(function ($q) use ($user) {
            $q->where('reportable_type', User::class)->where('reportable_id', $user->id);
        })->orWhere(function ($q) use ($user) {
            $q->whereIn('reportable_id', $user->posts()->pluck('id'))
                ->where('reportable_type', \App\Models\Post::class);
        })->orWhere(function ($q) use ($user) {
            $q->whereIn('reportable_id', $user->comments()->pluck('id'))
                ->where('reportable_type', \App\Models\Comment::class);
        })->with('user')->latest()->take(10)->get();

        $auditHistory = AuditLog::where(function ($q) use ($user) {
            $q->where('auditable_type', User::class)->where('auditable_id', $user->id);
        })->orWhere('user_id', $user->id)->with('user')->latest()->take(15)->get();

        return Inertia::render('Admin/Users/Show', [
            'subject' => $user,
            'recentPosts' => $recentPosts,
            'recentComments' => $recentComments,
            'reportsAgainst' => $reportsAgainst,
            'auditHistory' => $auditHistory,
        ]);
    }

    /**
     * Change a user's role (Admin-only action).
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        if (Auth::user()->role !== UserRole::ADMIN) {
            abort(403, 'Only administrators can update user roles.');
        }

        if ($user->id === Auth::id()) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $oldRole = $user->role->value;
        $user->update(['role' => $validated['role']]);

        $this->auditService->log('user.role_change', $user, [
            'old_role' => $oldRole,
            'new_role' => $validated['role'],
        ]);

        return back()->with('success', "User role updated to {$validated['role']}.");
    }

    /**
     * Ban a user.
     */
    public function ban(Request $request, User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['ban' => 'You cannot ban your own account.']);
        }

        if ($user->role === UserRole::ADMIN && Auth::user()->role !== UserRole::ADMIN) {
            abort(403, 'Moderators cannot ban administrators.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user->update([
            'is_banned' => true,
            'ban_reason' => $validated['reason'],
        ]);

        $this->auditService->log('user.ban', $user, [
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', "{$user->name} has been banned.");
    }

    /**
     * Unban a user.
     */
    public function unban(User $user): RedirectResponse
    {
        $user->update([
            'is_banned' => false,
            'ban_reason' => null,
        ]);

        $this->auditService->log('user.unban', $user);

        return back()->with('success', "{$user->name} has been unbanned.");
    }
}
