<?php
namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\ContactMessage;
use App\Mail\NewContactAdminMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PageController extends Controller
{
    public function show(string $slug)
{
    $page = Page::where('slug', $slug)
        ->where('status', 'published')
        ->firstOrFail();

    return view('frontend.page', compact('page'));
}
    public function about()
    {
        $page = Page::where('slug', 'about')->where('status', 'published')->first();
        return view('frontend.about', compact('page'));
    }

    public function contact()
    {
        $page = Page::where('slug', 'contact')->where('status', 'published')->first();
        return view('frontend.contact', compact('page'));
    }

    public function faq()
    {
        $categories = \App\Models\FaqCategory::with('faqs')->get();
        return view('frontend.faq', compact('categories'));
    }

    public function privacyPolicy()
    {
        $page = \App\Models\Page::where('slug', 'privacy-policy')->where('status', 'published')->first();
        return view('frontend.privacy_policy', compact('page'));
    }

    public function terms()
    {
        $page = \App\Models\Page::where('slug', 'terms')->where('status', 'published')->first();
        return view('frontend.terms', compact('page'));
    }

    public function sendContact(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        ContactMessage::create($validated);

        Mail::to(config('agency.admin_email'))
            ->send(new NewContactAdminMail(
                name: $validated['name'],
                email: $validated['email'],
                contactSubject: $validated['subject'],
                messageText: $validated['message'],
            ));

        return back()->with('success', 'Your message has been sent successfully.');
    }
}
