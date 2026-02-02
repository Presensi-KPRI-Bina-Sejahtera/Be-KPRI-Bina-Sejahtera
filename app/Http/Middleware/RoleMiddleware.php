<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * @param  array<int, string>  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $roles = $this->normalizeRoles($roles);

        $user = $request->user() ?? auth('sanctum')->user();

        if (!$user) {
            abort(401, 'Anda belum masuk atau sesi Anda telah berakhir.');
        }

        if ($roles === []) {
            return $next($request);
        }

        $userRole = (string) ($user->role ?? '');

        if (!in_array($userRole, $roles, true)) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses sumber daya ini.');
        }

        return $next($request);
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    private function normalizeRoles(array $roles): array
    {
        $normalized = [];

        foreach ($roles as $roleChunk) {
            foreach (preg_split('/[|,]/', $roleChunk) ?: [] as $role) {
                $role = trim($role);
                if ($role !== '') {
                    $normalized[] = $role;
                }
            }
        }

        return array_values(array_unique($normalized));
    }
}
