<?php
namespace App\Http\Controllers;

use App\Models\ProjectMessage;
use App\Models\ProjectFile;
use App\Models\Order;
use App\Models\Project;
use App\Support\ClientPortalAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientDashboardController extends Controller
{
    public function __construct(private ClientPortalAccess $portalAccess) {}

public function index()
{
    $client = $this->portalAccess->currentClient();
    $projects = $client->projects()
        ->with([
            'latestMessage.sender',
            'order',
        ])
        ->latest()
        ->take(8)
        ->get();

    $orders = $client->orders()
        ->with('project')
        ->latest()
        ->take(5)
        ->get();

    $recentMessages = ProjectMessage::whereIn('project_id', $client->projects()->select('id'))
        ->where('sender_id', '!=', Auth::id())
        ->with('project')
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get();

    $projectCounts = Project::query()
        ->where('client_id', $client->id)
        ->selectRaw('status, COUNT(*) as total')
        ->groupBy('status')
        ->pluck('total', 'status');

    $orderCounts = Order::query()
        ->where('client_id', $client->id)
        ->selectRaw('status, COUNT(*) as total')
        ->groupBy('status')
        ->pluck('total', 'status');

    $stats = [
        'total_projects'     => (int) $projectCounts->sum(),
        'active_projects'    => (int) ($projectCounts['in_progress'] ?? 0),
        'completed_projects' => (int) ($projectCounts['completed'] ?? 0),
        'pending_orders' => (int) ($orderCounts['pending'] ?? 0),
        'total_orders' => (int) $orderCounts->sum(),
    ];

    $purchases = \App\Models\Purchase::where('user_id', auth()->id())
        ->with(['version.product'])
        ->latest()
        ->take(6)
        ->get();

    $subscription = \App\Models\Subscription::where('user_id', auth()->id())
        ->with('plan')
        ->whereIn('status', ['active', 'pending'])
        ->latest()
        ->first();
    return view('client-dashboard.index', compact('projects', 'orders', 'recentMessages', 'stats', 'purchases', 'subscription'));
}

    public function project($id)
    {
        $client = $this->portalAccess->currentClient();
        $project = $client->projects()
            ->with(['order.services', 'order.features'])
            ->findOrFail($id);
        $messages = $project->messages()
            ->with('sender')
            ->orderBy('created_at')
            ->paginate(50, ['*'], 'messages_page');
        $files = $project->files()
            ->with('uploader')
            ->orderBy('created_at', 'desc')
            ->get();
        return view('client-dashboard.project', compact('project', 'messages', 'files'));
    }

    public function sendMessage(Request $request, $projectId)
    {
        $project = $this->portalAccess->ownedProjectOrFail($projectId);
        $validated = $request->validate(['message' => 'required|string|max:1000']);

        ProjectMessage::create([
            'project_id' => $project->id,
            'sender_id'  => Auth::id(),
            'message'    => $validated['message'],
        ]);
        return back()->with('success', 'Message sent successfully.');
    }

    public function uploadFile(Request $request, $projectId)
    {
        $project = $this->portalAccess->ownedProjectOrFail($projectId);

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,webp,txt,doc,docx,xls,xlsx,zip',
            ],
        ]);

        $path = $request->file('file')->store("project-files/{$project->id}", 'local');

        ProjectFile::create([
            'project_id'  => $project->id,
            'file_path'   => $path,
            'uploaded_by' => Auth::id(),
        ]);
        return back()->with('success', 'File uploaded successfully.');
    }

    public function editProfile()
    {
        return view('profile.edit');
    }

    public function downloadFile($projectId, $fileId)
    {
        [, $file] = $this->portalAccess->ownedProjectFileOrFail($projectId, $fileId);
        $downloadName = Str::of(basename($file->file_path))
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '_')
            ->value();

        if (Storage::disk('local')->exists($file->file_path)) {
            return Storage::disk('local')->download($file->file_path, $downloadName);
        }

        if (Storage::disk('public')->exists($file->file_path)) {
            return Storage::disk('public')->download($file->file_path, $downloadName);
        }

        abort(404);
    }
}
