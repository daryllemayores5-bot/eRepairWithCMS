<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        abort_unless($request->hasSession(), 419, 'Initialize a first-party session before registration.');
        $user = DB::transaction(function () use ($request) {
            $user = new User($request->safe()->only(['name', 'email', 'password']));
            $user->role = $request->validated('role');
            $user->save();
            if ($user->role === 'technician') {
                $user->technicianProfile()->create($request->safe()->only(['city', 'province']));
            }

            return $user;
        });
        $user->refresh();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request)
    {
        abort_unless($request->hasSession(), 419, 'Initialize a first-party session before signing in.');
        if (! Auth::guard('web')->attempt([...$request->validated(), 'account_status' => 'active'])) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect, or the account is inactive.']);
        }
        $request->session()->regenerate();

        return new UserResource(Auth::guard('web')->user());
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function forgot(ForgotPasswordRequest $request)
    {
        Password::sendResetLink($request->validated());

        return response()->json(['message' => 'If that account exists, a password reset link has been sent.']);
    }

    public function reset(ResetPasswordRequest $request)
    {
        $result = Password::reset($request->validated(), function (User $user, string $password) {
            $user->password = $password;
            $user->remember_token = Str::random(60);
            $user->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            event(new PasswordReset($user));
        });
        if ($result !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($result)]);
        }

        return response()->json(['message' => 'Password reset. You can now sign in.']);
    }

    public function profile(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $request->user()->update($data);

        return new UserResource($request->user());
    }
}
