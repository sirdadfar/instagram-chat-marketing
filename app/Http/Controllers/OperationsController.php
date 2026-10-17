<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\AutomationRun;
use App\Models\Contact;
use App\Models\MediaLibrary;
use App\Models\MessageTemplate;
use App\Models\WebhookEvent;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OperationsController extends Controller
{
    public function contacts(Request $request)
    {
        $query = Contact::with('tags')
            ->whereIn('instagram_account_id', Tenancy::visibleAccountIds())
            ->latest('last_seen_at');

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($builder) use ($q) {
                $builder->where('username', 'like', "%{$q}%")
                    ->orWhere('full_name', 'like', "%{$q}%")
                    ->orWhere('external_user_id', 'like', "%{$q}%");
            });
        }

        if ($request->boolean('human')) {
            $query->where('needs_human', true);
        }

        return view('operations.contacts', [
            'contacts' => $query->paginate(30)->withQueryString(),
        ]);
    }

    public function templates()
    {
        return view('operations.templates', [
            'templates' => Tenancy::scope(MessageTemplate::query())->latest()->paginate(30),
        ]);
    }

    public function storeTemplate(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'string', 'max:40'],
        ]);

        $slug = Str::slug($data['name']) ?: 'template-' . Str::random(8);
        $base = $slug;
        $i = 1;
        while (MessageTemplate::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $data['body'], $matches);

        MessageTemplate::create([
            ...$data,
            'user_id' => auth()->id(),
            'slug' => $slug,
            'language' => 'fa',
            'variables' => array_values(array_unique($matches[1] ?? [])),
            'active' => true,
        ]);

        return back()->with('success', 'قالب پیام ساخته شد.');
    }

    public function toggleTemplate(MessageTemplate $template)
    {
        Tenancy::scope(MessageTemplate::whereKey($template->id))->firstOrFail();
        $template->update(['active' => ! $template->active]);
        return back()->with('success', 'وضعیت قالب تغییر کرد.');
    }

    public function deleteTemplate(MessageTemplate $template)
    {
        Tenancy::scope(MessageTemplate::whereKey($template->id))->firstOrFail();
        $template->delete();
        return back()->with('success', 'قالب حذف شد.');
    }

    public function media()
    {
        return view('operations.media', [
            'media' => Tenancy::scope(MediaLibrary::query())->latest()->paginate(30),
        ]);
    }

    public function storeMedia(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:image,video,audio,file'],
            'url' => ['required', 'url', 'max:2048'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        MediaLibrary::create($data + ['user_id' => auth()->id(), 'active' => true]);

        return back()->with('success', 'رسانه به کتابخانه اضافه شد.');
    }

    public function deleteMedia(MediaLibrary $media)
    {
        Tenancy::scope(MediaLibrary::whereKey($media->id))->firstOrFail();
        $media->delete();
        return back()->with('success', 'رسانه حذف شد.');
    }

    public function health()
    {
        $automationIds = Tenancy::visibleAutomationIds();
        $accountIds = Tenancy::visibleAccountIds();
        $failed = AutomationLog::whereIn('automation_id', $automationIds)->where('status', 'failed')->latest()->take(8)->get();
        $running = AutomationRun::whereIn('automation_id', $automationIds)->where('status', 'running')->count();

        return view('operations.health', [
            'automationCount' => Automation::whereIn('instagram_account_id', $accountIds)->count(),
            'activeAutomationCount' => Automation::whereIn('instagram_account_id', $accountIds)->where('status', 'active')->count(),
            'contactCount' => Contact::whereIn('instagram_account_id', $accountIds)->count(),
            'webhookCount' => auth()->user()->isAdmin() ? WebhookEvent::count() : 0,
            'failedCount' => AutomationLog::whereIn('automation_id', $automationIds)->where('status', 'failed')->count(),
            'runningCount' => $running,
            'failed' => $failed,
        ]);
    }
}
