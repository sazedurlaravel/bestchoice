<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user()?->canAccessAdmin() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $subject = collect($request->route()?->parameters() ?? [])
                ->first(fn ($value) => is_object($value) && method_exists($value, 'getKey'));

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => $request->route()?->getName() ?? $request->method().' '.$request->path(),
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'metadata' => $this->safeMetadata($request->except(['_token', '_method', 'password', 'password_confirmation', 'current_password', 'api_key', 'secret_key'])),
                'status_code' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }

    /** @param array<string, mixed> $data */
    private function safeMetadata(array $data): array
    {
        return array_map(function ($value) {
            if ($value instanceof UploadedFile) {
                return '[uploaded file]';
            }
            if (is_array($value)) {
                return $this->safeMetadata($value);
            }
            if (is_scalar($value) || $value === null) {
                return $value;
            }

            return '['.get_debug_type($value).']';
        }, $data);
    }
}
