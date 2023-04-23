<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function updateProfileData(Request $request)
    {
        try {
            $user = $request->user();

            Cache::forget('user:' . $user->id);

            $validateData = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:60'],
                'description' => ['nullable', 'string'],
                'profile' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048']
            ]);

            if ($validateData->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validateData->errors()
                ]);
            }

            $profile = $user->profile;

            if ($request->hasFile('profile')) {
                Storage::makeDirectory('public/profiles');
                $url = Storage::put('public/profiles/' . uniqid(), $request->file('profile'));

                $previousPhotoUrl = $profile->profile;

                if ($previousPhotoUrl) {
                    Storage::delete($previousPhotoUrl);
                    $previousPhotoDirectory = dirname($previousPhotoUrl);
                    Storage::deleteDirectory($previousPhotoDirectory);
                }

                $profile->profile = $url;
            }

            $profile->name = $request->input('name');
            $profile->description = $request->input('description');
            $profile->save();

            return response()->json([
                'success' => true,
                'message' => 'Se han actualizado correctamente los datos de perfil.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function updatePassword(Request $request)
    {
        try {
            $user = $request->user();

            $validateData = Validator::make($request->all(), [
                'old_password' => ['required', 'string'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            if ($validateData->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validateData->errors(),
                ]);
            }

            $oldPassword = $request->input('old_password');
            $newPassword = $request->input('password');

            if (!Hash::check($oldPassword, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La contraseña actual no es válida.',
                ]);
            }

            $user->password = Hash::make($newPassword);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Contraseña actualizada correctamente.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }
    }


    public function changeNSFW(Request $request)
    {
        try {
            $user = $request->user();
            Cache::forget('user:' . $user->id);

            $validateData = Validator::make($request->all(), [
                'nsfw' => ['required', 'boolean'],
            ]);

            if ($validateData->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validateData->errors()
                ]);
            }

            $message = $request->input('nsfw') == 1 ? 'Opción NSFW activada.' : 'Opción NSFW desactivada.';

            $user->show_nsfw = $request->input('nsfw');
            $user->save();

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function deleteUser(Request $request)
    {
        try {
            $user = $request->user();
            $user = User::find($user->id);
            Cache::forget('user:' . $user->id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Al parecer el usuario no existe o no esta activo.'
                ], 404);
            }

            $user->status = 0;
            $user->tokens()->delete();
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Su cuenta (' . $user->email . ') se ha eliminado correctamente. Si desea volver a utilizar nuestros servicios, póngase en contacto con el soporte técnico para reactivar su cuenta.',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
