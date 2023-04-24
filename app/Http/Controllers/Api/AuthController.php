<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ConfirmMailable;
use App\Mail\ForgotMailable;
use App\Mail\PasswordNotificationMailable;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validateData = Validator::make($request->all(), [
                'email' => ['required', 'email', 'unique:users,email,' . $request->id],
                'password' => ['required', 'confirmed', 'min:8'],
                'name' => ['required', 'min:4', 'max:50'],
                'birthday' => ['required', 'date'],
            ]);

            if ($validateData->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validateData->errors()
                ]);
            }

            $input = $request->all();
            $input['password'] = Hash::make($input['password']);
            $token = uniqid(base64_encode(Str::random(60)));
            $input['verify_token'] = $token;

            $user = User::create($input);

            $profile = Profile::create([
                'user_id' => $user->id,
                'name' => $input['name'],
                'birthday' => $input['birthday'],
                'profile' => null,
                'description' => null
            ]);

            if ($user && $profile) {
                $email = new ConfirmMailable($token, $profile['name']);
                Mail::to($user->email)->send($email);

                return response()->json([
                    'success' => true,
                    'message' => 'Registro exitoso. Por favor, revise su correo electrónico para confirmar su cuenta.',
                    'token' => $user->createToken('register token', ['*'], now()->addDays(31))->plainTextToken
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se pudo registrar al usuario. Por favor, inténtelo de nuevo más tarde.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $validateData = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            $user = User::where('email', $validateData['email'])->first();

            if (!$user || $user->status === 0) {
                return response()->json([
                    'success' => false,
                    'message' => "El usuario no está registrado."
                ]);
            }

            if (!$user->verified) {
                return response()->json([
                    'success' => false,
                    'message' => "Aún no ha verificado su cuenta de correo electrónico. Por favor, revise su bandeja de entrada y verifique su cuenta."
                ]);
            }

            if (Auth::attempt($validateData)) {
                $user->tokens()->delete();
                $user->save();

                return response()->json([
                    'success' => true,
                    'token' => $user->createToken('login token', ['*'], now()->addDays(31))->plainTextToken,
                    'message' => 'Ha iniciado sesión correctamente.'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'La dirección de correo electrónico o la contraseña no son válidas.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $user->currentAccessToken()->delete();
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'La sesión ha sido cerrada exitosamente. ¡Esperamos verte pronto de nuevo!'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function user(Request $request)
    {
        try {
            $user = $request->user();
            $userId = $user->id;

            if ($user->status !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lo sentimos, su cuenta está inactiva. Por favor, póngase en contacto con el administrador para obtener más información.'
                ], 401);
            }

            $user = Cache::remember('user:' . $userId, 60, function () use ($userId) {
                $user = User::with('profile')->find($userId);

                if ($user->profile->profile != null) {
                    $user->profile->profile = url(Storage::url($user->profile->profile));
                }

                unset($user->profile->user_id);

                return $user;
            });

            return response()->json([
                'success' => true,
                'user' => $user,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $validateData = $request->validate([
                'email' => ['required', 'email'],
            ]);

            $user = User::where('email', $validateData['email'])->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => "No se encontró un usuario registrado con esa dirección de correo electrónico. Por favor, regístrese para continuar."
                ]);
            }

            $token = Password::createToken($user);

            $user->update([
                'reset_password_token' => $token
            ]);

            $user->with('profile')->first();

            $email = new ForgotMailable($token, $user->profile['name']);
            Mail::to($user->email)->send($email);

            return response()->json([
                'success' => true,
                'message' => 'Se ha enviado un email de restablecimiento de contraseña a su correo electrónico. Por favor revise su bandeja de entrada. Si no lo recibe en los próximos minutos, revise su carpeta de correo no deseado.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $validateData = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'confirmed', 'min:8'],
                'token' => ['required']
            ]);

            $response = Password::reset(
                $validateData,
                function ($user, $password) {
                    $user->password = Hash::make($password);
                    $user->save();
                }
            );

            if ($response !== Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => false,
                    'message' => "No se ha podido restablecer la contraseña. Por favor, revise que la dirección de correo electrónico sea correcta y vuelva a intentarlo.",
                ]);
            }

            Mail::to($validateData['email'])->send(new PasswordNotificationMailable());

            return response()->json([
                'success' => true,
                'message' => 'Se ha restablecido correctamente su contraseña. Ahora puede iniciar sesión.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function verify($token)
    {
        try {
            $user = User::where('verify_token', $token)->first();

            if ($user) {
                $user->verified = true;
                $user->verify_token = null;
                $user->markEmailAsVerified();
                $user->save();

                Cache::forget('user:' . $user->id);

                return redirect('http://localhost:5173/', 301);
            } else {
                return "El token no es válido...";
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
