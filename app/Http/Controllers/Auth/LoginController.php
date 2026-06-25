<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{
   // GitHub
   public function gitHubRedirect(): RedirectResponse
   {
      return Socialite::driver('github')->redirect();
   }

   public function gitHubCallback(): RedirectResponse
   {
      $githubUser = Socialite::driver('github')->user();
      $user = User::updateOrCreate([
         'provider' => 'github',
         'provider_id' => $githubUser->getId(),
      ], [
         'name' => $githubUser->getName(),
         'email' => $githubUser->getEmail(),
         'provider_token' => $githubUser->token,
         'password' => bcrypt(str()->random(16)),
      ]);

      Auth::login($user, remember: true);

      return redirect('/dashboard');
   }

   // Google
   public function googleRedirect(): RedirectResponse
   {
      return Socialite::driver('google')->redirect();
   }

   public function googleCallback(): RedirectResponse
   {
      $googleUser = Socialite::driver('google')->user();
      $user = User::updateOrCreate([
         'provider' => 'google',
         'provider_id' => $googleUser->getId(),
      ], [
         'name' => $googleUser->getName(),
         'email' => $googleUser->getEmail(),
         'provider_token' => $googleUser->token,
         'password' => bcrypt(str()->random(16)),
      ]);

      Auth::login($user, remember: true);

      return redirect('/dashboard');
   }
}
