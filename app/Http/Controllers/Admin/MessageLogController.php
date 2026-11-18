<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'channel' => ['nullable', 'in:mail,sms,whatsapp,push'],
            'status' => ['nullable', 'in:sent,failed,skipped'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return view('admin.messages.index', [
            'filters' => $filters,
            'logs' => MessageLog::query()
                ->when($filters['channel'] ?? null, fn ($q, $c) => $q->where('channel', $c))
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['q'] ?? null, fn ($q, $t) => $q->where('recipient', 'like', '%'.addcslashes($t, '%_\\').'%'))
                ->latest('id')->paginate(30)->withQueryString(),
            'failed24h' => MessageLog::query()->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
        ]);
    }
}
