<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 50), 200);
        $logs = $this->filtered($request)->orderByDesc('created_at')->paginate($perPage);

        return ApiResponse::paginated(AuditLogResource::collection($logs->load('bucket')), $logs);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->orderBy('created_at');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['timestamp', 'actor_type', 'actor_id', 'action', 'bucket_id', 'object_key', 'ip_address', 'status']);

            $query->chunk(500, function ($chunk) use ($out) {
                /** @var AuditLog $log */
                foreach ($chunk as $log) {
                    fputcsv($out, [
                        $log->created_at?->toAtomString(),
                        $log->actor_type->value,
                        $log->actor_id,
                        $log->action,
                        $log->bucket_id,
                        $log->object_key,
                        $log->ip_address,
                        $log->status->value,
                    ]);
                }
            });

            fclose($out);
        }, 'audit-logs-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = AuditLog::query();

        if ($request->filled('actor_type')) {
            $query->where('actor_type', $request->string('actor_type'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->string('action').'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('bucket_id')) {
            $query->where('bucket_id', $request->integer('bucket_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->string('to'));
        }

        return $query;
    }
}
