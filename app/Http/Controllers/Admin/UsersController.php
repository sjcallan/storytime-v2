<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    /**
     * Display the admin users list.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $allowedSortColumns = ['name', 'email', 'created_at', 'books_count', 'chapters_count'];
        if (! in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $query = User::query()
            ->select('users.*')
            ->selectSub(
                Book::selectRaw('COUNT(*)')
                    ->whereColumn('books.user_id', 'users.id')
                    ->withTrashed(false),
                'books_count'
            )
            ->selectSub(
                Chapter::selectRaw('COUNT(*)')
                    ->whereColumn('chapters.user_id', 'users.id')
                    ->withTrashed(false),
                'chapters_count'
            );

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sortBy, $sortDirection);

        $users = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/Users', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }
}
