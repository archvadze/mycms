<?php

use App\Http\Controllers\EmailAttachmentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\DomainSearchController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\PollController;
use Illuminate\Support\Facades\Route;


Route::get('/', [HomeController::class, 'index'])
    ->name('home');

Route::get('/services', [ServiceController::class, 'index'])
    ->name('services')
    ->middleware('module:module_services');

Route::get('/portfolio', [PortfolioController::class, 'index'])
    ->name('portfolio')
    ->middleware('module:module_portfolio');

Route::get('/blog', [PublicationController::class, 'index'])
    ->name('blog')
    ->middleware('module:module_blog');

Route::get('/blog/{slug}', [PublicationController::class, 'show'])
    ->name('blog.show')
    ->middleware('module:module_blog');

Route::get('/guides', [GuideController::class, 'index'])
    ->name('guides')
    ->middleware('module:module_guides');

Route::get('/guides/{slug}', [GuideController::class, 'show'])
    ->name('guides.show')
    ->middleware('module:module_guides');

Route::get(
    '/sitemap.xml',
    [App\Http\Controllers\SitemapController::class, 'index']
)->name('sitemap');

Route::get('/about', [PageController::class, 'about'])
    ->name('about');

Route::get('/faq', [PageController::class, 'faq'])
    ->name('faq');

Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])
    ->name('privacy-policy');

Route::get('/terms', [PageController::class, 'terms'])
    ->name('terms');

Route::post(
    '/newsletter/subscribe',
    [App\Http\Controllers\NewsletterController::class, 'subscribe']
)->name('newsletter.subscribe');

Route::get(
    '/newsletter/unsubscribe/{token}',
    [App\Http\Controllers\NewsletterController::class, 'unsubscribe']
)->name('newsletter.unsubscribe');

Route::get('/contact', [PageController::class, 'contact'])
    ->name('contact');

Route::post('/contact', [PageController::class, 'sendContact'])
    ->name('contact.send');


Route::middleware(['auth', 'verified', 'client'])->group(function () {
    Route::get(
        '/payment/{orderId}/create',
        [PaymentController::class, 'createPayment']
    )->name('payment.create');

    Route::get(
        '/payment/{orderId}/success',
        [PaymentController::class, 'paymentSuccess']
    )->name('payment.success');

    Route::get(
        '/payment/{orderId}/cancel',
        [PaymentController::class, 'paymentCancel']
    )->name('payment.cancel');
});


Route::post('/domain-search', [DomainSearchController::class, 'search'])
    ->name('domain.search');


Route::middleware(['auth', 'verified', 'client'])->group(function () {
    Route::get('/order', [OrderController::class, 'create'])
        ->name('order.create');

    Route::post('/order', [OrderController::class, 'store'])
        ->name('order.store');

    Route::get('/order/success/{orderId}', [OrderController::class, 'success'])
        ->name('order.success');
});


Route::middleware(['auth', 'verified', 'client'])
    ->prefix('client-dashboard')
    ->name('client-dashboard.')
    ->group(function () {
        Route::get('/', [ClientDashboardController::class, 'index'])
            ->name('index');

        Route::get('/project/{id}', [ClientDashboardController::class, 'project'])
            ->name('project');

        Route::post(
            '/project/{projectId}/message',
            [ClientDashboardController::class, 'sendMessage']
        )->middleware('throttle:30,1')->name('send-message');

        Route::post(
            '/project/{projectId}/upload',
            [ClientDashboardController::class, 'uploadFile']
        )->middleware('throttle:20,60')->name('upload-file');

        Route::get(
            '/project/{projectId}/file/{fileId}/download',
            [ClientDashboardController::class, 'downloadFile']
        )->name('download-file');

        Route::get('/profile', [ClientDashboardController::class, 'editProfile'])
            ->name('profile');
    });


Route::get('/dashboard', DashboardRedirectController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});


Route::get('/page/{slug}', [PageController::class, 'show'])
    ->name('page.show');


Route::get(
    '/auth/{provider}/redirect',
    [SocialAuthController::class, 'redirect']
)->name('social.redirect');

Route::get(
    '/auth/{provider}/callback',
    [SocialAuthController::class, 'callback']
)->name('social.callback');


Route::middleware(['module:module_shop'])->group(function () {
    Route::get(
        '/shop',
        [App\Http\Controllers\ShopController::class, 'index']
    )->name('shop.index');

    Route::get(
        '/shop/{slug}',
        [App\Http\Controllers\ShopController::class, 'show']
    )->name('shop.show');
});


Route::middleware([
    'auth',
    'verified',
    'client',
    'module:module_shop',
])->group(function () {
    Route::get(
        '/shop/{slug}/checkout',
        [App\Http\Controllers\ShopController::class, 'checkout']
    )->name('shop.checkout');

    Route::get(
        '/shop/{slug}/success',
        [App\Http\Controllers\ShopController::class, 'checkoutSuccess']
    )->name('shop.checkout.success');

    Route::get(
        '/shop/{slug}/cancel',
        [App\Http\Controllers\ShopController::class, 'checkoutCancel']
    )->name('shop.checkout.cancel');

    Route::post(
        '/purchase/{purchase}/download',
        [App\Http\Controllers\ShopController::class, 'download']
    )->name('purchase.download');
});


Route::middleware(['auth', 'verified', 'client'])
    ->controller(App\Http\Controllers\SubscriptionController::class)
    ->group(function () {
        Route::get('/subscribe', 'plans')
            ->name('subscription.plans');

        Route::post('/subscribe/{plan}', 'request')
            ->name('subscription.request');

        Route::post('/subscription/cancel', 'cancel')
            ->name('subscription.cancel');
    });


Route::middleware('auth')->group(function () {
    Route::get(
        '/manage/email-messages/{message}/attachments/{attachmentId}',
        EmailAttachmentController::class
    )->name('email-messages.attachments.download');
});


Route::get(
    '/testimonials',
    [App\Http\Controllers\TestimonialController::class, 'index']
)->name('testimonials');

Route::post(
    '/publications/{publication}/comments',
    [CommentController::class, 'store']
)
    ->name('comments.store')
    ->middleware(['auth', 'verified']);


Route::prefix('polls')
    ->name('polls.')
    ->group(function () {
        Route::get('/', [PollController::class, 'index'])
            ->name('index');

        Route::get('/{id}', [PollController::class, 'show'])
            ->name('show');

        Route::post('/{id}/vote', [PollController::class, 'vote'])
            ->name('vote');
    });


require __DIR__.'/auth.php';


// Dynamic CMS pages - MUST be last
Route::get(
    '/{slug}',
    [PageController::class, 'show']
)
    ->name('page.dynamic')
    ->where('slug', '[a-z0-9-]+');
