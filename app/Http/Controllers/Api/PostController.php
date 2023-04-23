<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LikedPost;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PostController extends Controller
{
    public function index()
    {
        try {
            $posts = Post::where('status', 1)
                ->where('nsfw', 0)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $posts
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function posts(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->show_nsfw) {
                $posts = Post::where('status', 1)
                    ->get();
            } else {
                $posts = Post::where('status', 1)
                    ->where('nsfw', 0)
                    ->get();
            }

            foreach ($posts as $post) {
                $post->like = LikedPost::where('post_id', $post->id)
                    ->where('user_id', $user->id)
                    ->where('like', 1)
                    ->exists() ? 1 : 0;
            }

            return response()->json([
                'success' => true,
                'data' => $posts
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function create(Request $request)
    {
        try {
            $user = $request->user();
            $input = $request->all();

            if (!$user->verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Necesita verificar su cuenta para poder crear un post.'
                ]);
            }

            $validateData = Validator::make($input, [
                'title' => ['required', 'min:4', 'max:50'],
                'status' => ['required', 'max:1', 'in:1,2'],
                'image' => ['required', 'image'],
                'nsfw' => ['required', 'boolean'],
            ]);

            if ($validateData->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validateData->errors()
                ]);
            }

            $input['slug'] = Str::slug($input['title'] . '-' . $user->id);

            if ($post = Post::where('slug', $input['slug'])->first()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lo sentimos, no se pudo crear el post. Ya existe otro post con el mismo título, por favor intente con otro título.'
                ]);
            }

            if ($request->file('image')) {
                Storage::makeDirectory('public/posts');

                $url = Storage::put('public/posts/' . uniqid(), $request->file('image'));
                $input['image_path'] = $url;
                $input['image'] = url(Storage::url($url));
            }

            $input['user_id'] = $user->id;
            $input['created_at'] = date('Y-m-d H:i:s');
            $input['updated_at'] = date('Y-m-d H:i:s');
            $input['nsfw'] = $input['nsfw'] ? 1 : 0;

            $post = Post::create($input);

            if ($post) {
                return response()->json([
                    'success' => true,
                    'message' => 'El post se ha creado correctamente.'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Lo sentimos, ha ocurrido un error técnico al crear el post. Por favor, inténtelo de nuevo más tarde.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, $slug)
    {
        try {
            $user = $request->user();
            $post = Post::where('slug', $slug)->where('user_id', $user->id)->first();

            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lo siento, no se encontró el post solicitado.'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $post
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $user = $request->user();
            $input = $request->all();
            $post = Post::where('slug', $request->slug)->where('user_id', $user->id)->first();

            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el post solicitado o no tiene permiso para editarlo. Asegúrese de que el post existe y que está autorizado a editarlo.'
                ]);
            }

            $validateData = Validator::make($input, [
                'title' => ['required', 'min:4', 'max:50'],
                'status' => ['required', 'max:1', 'in:1,2'],
                'image' => ['image'],
                'nsfw' => ['required', 'boolean'],
            ]);

            if ($validateData->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validateData->errors()
                ]);
            }

            $post->title = $input['title'];
            $post->status = $input['status'];
            $post->nsfw = $input['nsfw'] ? 1 : 0;

            if ($request->file('image')) {
                Storage::delete($post->image_path);
                Storage::makeDirectory('public/posts');
                $url = Storage::put('public/posts/' . uniqid(), $request->file('image'));
                $post->image_path = $url;
                $post->image = url(Storage::url($url));
            }

            $post->updated_at = date('Y-m-d H:i:s');

            if ($post->save()) {
                return response()->json([
                    'success' => true,
                    'message' => '¡El post se ha actualizado exitosamente!'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Lo siento, ha habido un error al actualizar el post. Por favor, intenta de nuevo más tarde.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request, $slug)
    {
        try {
            $user = $request->user();
            $post = Post::where('slug', $slug)->where('user_id', $user->id)->first();

            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el post solicitado o no tiene permiso para eliminarlo. Asegúrese de que el post existe y que está autorizado a eliminarlo.'
                ]);
            }

            $post->status = 0;

            if ($post->save()) {
                return response()->json([
                    'success' => true,
                    'message' => '¡El post se ha eliminado exitosamente!'
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function download(Post $slug)
    {
        $image_path = $slug->image_path;

        if (Storage::disk('local')->exists($image_path)) {
            return Storage::disk('local')->download($image_path);
        } else {
            return response('', 404);
        }
    }

    public function favoritesPosts(Request $request)
    {
        try {
            $user = $request->user();
            $posts = LikedPost::where('user_id', $user->id)
                ->where('like', 1)
                ->with('post')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $posts
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function myPosts(Request $request)
    {
        try {
            $user = $request->user();

            $posts = $user->posts()
                ->where('status', '!=', 0)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $posts
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function likePost(Request $request)
    {
        try {
            $user = $request->user();
            $post = Post::where('slug', $request->slug)->where('status', 1)->first();

            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lo siento, no se encontró el post solicitado.'
                ]);
            }

            $like = LikedPost::where('user_id', $user->id)->where('post_id', $post->id)->first();

            if (!$like) {
                $like = new LikedPost();
                $like->user_id = $user->id;
                $like->post_id = $post->id;
                $like->like = 1;
                $like->save();

                return response()->json([
                    'success' => true,
                    'message' => 'El post se a agregado a favoritos.'
                ]);
            } else {
                $like->like = !$like->like;
                $like->save();

                return response()->json([
                    'success' => true,
                    'message' => $like->like ? 'El post se a agregado a favoritos.' : 'El post se a eliminado de favoritos.'
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
