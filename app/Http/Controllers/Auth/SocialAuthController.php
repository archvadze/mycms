<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    private const PROVIDERS = [
        'google' => 'google',
        'facebook' => 'facebook',
        'twitter' => 'twitter-oauth-2',
    ];

    private function driver(string $provider): string
    {
        abort_unless(isset(self::PROVIDERS[$provider]), 404);

        return self::PROVIDERS[$provider];
    }

    private function configured(string $provider): bool
    {
        $config = config('services.' . $this->driver($provider), []);

        return filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null);
    }

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $driver = $this->driver($provider);

        if (! $this->configured($provider)) {
            return redirect()->route('login')
                ->with('error', 'This sign-in provider is unavailable.');
        }

        $request->session()->put('social_auth_intent', 'login');

        return Socialite::driver($driver)->redirect();
    }

    public function connect(Request $request, string $provider): RedirectResponse
    {
        $driver = $this->driver($provider);

        abort_unless(
            $request->user() && $request->user()->status === 'active',
            403
        );

        if (! $this->configured($provider)) {
            return back()->with('error', 'This provider is unavailable.');
        }

        $request->session()->put('social_auth_intent', 'link');
        $request->session()->put('social_auth_link_user_id', $request->user()->id);

        return Socialite::driver($driver)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $driver = $this->driver($provider);

        if (! $this->configured($provider)) {
            return redirect()->route('login')
                ->with('error', 'This sign-in provider is unavailable.');
        }

        $intent = $request->session()->pull('social_auth_intent');
        $linkUserId = $request->session()->pull('social_auth_link_user_id');

        if (! in_array($intent, ['login', 'link'], true)) {
            return redirect()->route('login')
                ->with('error', 'Authentication session expired. Please retry.');
        }

        try {
            // Socialite validates the OAuth state for supported providers.
            $socialUser = Socialite::driver($driver)->user();
        } catch (Throwable $exception) {
            return redirect()->route('login')
                ->with('error', 'Social authentication failed. Please retry.');
        }

        $providerId = trim((string) $socialUser->getId());

        if ($providerId === '') {
            return redirect()->route('login')
                ->with('error', 'Provider identity could not be verified.');
        }

        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerId)
            ->first();

        if ($intent === 'link') {
            $user = $request->user();

            abort_unless(
                $user
                && $user->status === 'active'
                && (int) $user->id === (int) $linkUserId,
                403
            );

            if ($account && $account->user_id !== $user->id) {
                return redirect()->route('client-dashboard.profile')
                    ->with('error', 'This social account is already linked.');
            }

            if ($user->socialAccounts()
                ->where('provider', $provider)
                ->where('provider_user_id', '!=', $providerId)
                ->exists()) {
                return redirect()->route('client-dashboard.profile')
                    ->with('error', 'Another account from this provider is linked.');
            }

            if (! $account) {
                try {
                    $user->socialAccounts()->create([
                        'provider' => $provider,
                        'provider_user_id' => $providerId,
                    ]);
                } catch (\Illuminate\Database\QueryException $exception) {
                    return redirect()->route('client-dashboard.profile')
                        ->with(
                            'error',
                            'This social account could not be connected. '
                            . 'Refresh your profile and try again.'
                        );
                }
            }

            return redirect()->route('client-dashboard.profile')
                ->with('status', 'Social account connected.');
        }

        if ($account) {
            $user = $account->user;

            if (! $user || $user->status !== 'active') {
                return redirect()->route('login')
                    ->with('error', 'This account is unavailable.');
            }

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        $email = $socialUser->getEmail();

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('login')
                ->with('error', 'This provider did not supply an email address.');
        }

        $email = Str::lower(trim($email));

        // Temporary Google-only compatibility for pre-migration accounts.
        // Remove after existing users have explicitly linked their Google IDs.
        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser) {
            // Never use legacy email fallback for an account
            // that already has an explicitly linked Google identity.
            if ($existingUser->socialAccounts()
                ->where('provider', 'google')
                ->exists()) {
                return redirect()->route('login')->with(
                    'error',
                    'This account is linked to a different Google identity.'
                );
            }

            $raw = $socialUser->getRaw();

            $verifiedGoogleEmail = $provider === 'google'
                && ($raw['email_verified'] ?? false) === true;

            if (
                config('services.google.legacy_email_fallback', false)
                && $verifiedGoogleEmail
                && $existingUser->email_verified_at !== null
                && $existingUser->status === 'active'
            ) {
                // No social_accounts row is created by this fallback.
                Auth::login($existingUser);
                $request->session()->regenerate();

                return redirect()->intended(route('dashboard'));
            }

            return redirect()->route('login')->with(
                'error',
                'This email already belongs to an account. '
                . 'Sign in with your existing credentials and connect Google.'
            );
        }

        $name = trim((string) (
            $socialUser->getName()
            ?: $socialUser->getNickname()
            ?: 'User'
        ));

        $name = Str::limit($name, 255, '');

        $raw = $socialUser->getRaw();
        $verified = $provider === 'google'
            && (
                ($raw['email_verified'] ?? false) === true
                || ($raw['verified_email'] ?? false) === true
            );

        if (! $verified) {
            return redirect()->route('login')->with(
                'error',
                'The provider could not verify your email address. '
                . 'Please use email registration or sign in and connect '
                . 'the provider from your profile.'
            );
        }

        try {
            $user = DB::transaction(function () use (
                $name,
                $email,
                $verified,
                $provider,
                $providerId
            ): User {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Str::random(64),
                    'email_verified_at' => $verified ? now() : null,
                ]);

                $user->assignRole('Client');

                Client::create([
                    'user_id' => $user->id,
                    'name' => $name,
                    'email' => $email,
                ]);

                $user->socialAccounts()->create([
                    'provider' => $provider,
                    'provider_user_id' => $providerId,
                ]);

                return $user;
            });
        } catch (Throwable $exception) {
            return redirect()->route('login')
                ->with('error', 'Account creation failed. Please retry.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
