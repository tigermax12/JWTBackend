<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Peticione;
use Illuminate\Http\Request;
use App\Models\Categoria;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class PeticioneController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['index', 'show']]);
    }
    public function index(Request $request)
    {
        try {
            $peticiones = Peticione::with('file')->get();

            $peticiones = $peticiones->map(function ($peticion) {
                if ($peticion->file) {

                    $peticion->file->file_url = url($peticion->file->file_path);
                }
                return $peticion;
            });

        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener las peticiones: ' . $e->getMessage()], 500);
        }
        return response()->json(['peticiones' => $peticiones], 200);
    }

    public function listMine(Request $request)
    {
        try {
            $user = Auth::user();

            $peticiones = Peticione::with('file') // cargar la relación del archivo
            ->where('user_id', $user->id)
                ->get();

            $peticiones = $peticiones->map(function ($peticion) {
                if ($peticion->file) {
                    $peticion->file->file_url = url($peticion->file->file_path);
                }
                return $peticion;
            });

            return response()->json(['peticiones' => $peticiones], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener tus peticiones: ' . $e->getMessage()], 500);
        }
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo' => 'required|max:255',
            'descripcion' => 'required',
            'destinatario' => 'required',
            'categoria_id' => 'required|exists:categorias,id',
            'file' => 'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422); // 422 Unprocessable Entity es más apropiado
        }

        try {
            $user = Auth::user();
            $category = Categoria::findOrFail($request->categoria_id);

            $peticion = new Peticione($request->only(['titulo', 'descripcion', 'destinatario']));
            $peticion->user()->associate($user);
            $peticion->categoria()->associate($category);
            $peticion->firmantes = 0;
            $peticion->estado = 'pendiente';
            $peticion->save();

            if ($peticion) {
                $fileModel = $this->fileUpload($request, $peticion->id);

                if ($fileModel) {
                    return response()->json([
                        'message' => 'Petición creada correctamente.',
                        'peticion' => $peticion,
                        'file' => $fileModel,
                    ], 201); // 201 Created
                }

                // Si falla la subida del archivo, borra la petición
                $peticion->delete();
                return response()->json(['error' => 'Error subiendo el archivo.'], 500);
            }

            return response()->json(['error' => 'Error al guardar la petición.'], 500);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Categoría no encontrada.'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al crear la petición.', 'details' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $peticion = Peticione::with('firmas')->findOrFail($id);
        $user = Auth::user();

        $yaFirmada = $user ? $peticion->firmas()->where('user_id', $user->id)->exists() : false;

        return response()->json([
            'peticion' => $peticion,
            'ya_firmada' => $yaFirmada,
        ]);
    }


    public function update(Request $request, $id)
    {
        try {
            $peticion = Peticione::findOrFail($id);
            $this->authorize('update', $peticion);
            $peticion->update($request->all());
            if ($request->hasFile('file')) {
                $peticion->file()->delete();
                $fileModel = $this->fileUpload($request, $peticion->id);

                if ($fileModel) {
                    return response()->json([
                        'message' => 'Petición creada correctamente.',
                        'peticion' => $peticion,
                        'file' => $fileModel,
                    ], 201); // 201 Created
                }

                // Si falla la subida del archivo, borra la petición
                $peticion->delete();
                return response()->json(['error' => 'Error subiendo el archivo.'], 500);
            }
            return response()->json($peticion, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Petición no encontrada'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al actualizar la petición'], 500);
        }
    }

    public function firmar(Request $request, $id)
    {
        try {
            $peticion = Peticione::findOrFail($id);
            $this->authorize('firmar', $peticion);
            $user = Auth::user();

            if ($peticion->firmas()->where('user_id', $user->id)->exists()) {
                return response()->json(['error' => 'Ya has firmado esta petición'], 400);
            }

            $peticion->firmas()->attach($user->id);
            $peticion->increment('firmantes');

            return response()->json($peticion, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Petición no encontrada'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al firmar la petición'], 500);
        }
    }

    public function cambiarEstado(Request $request, $id)
    {
        try {
            if (!Auth::check()) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }
            $peticion = Peticione::findOrFail($id);

            if ($request->user()->cannot('cambiarEstado', $peticion)) {
                return response()->json(['message' => 'No estás autorizado para realizar esta acción'], 403);
            }

            if ($peticion->estado === 'aceptada') {
                return response()->json(['message' => 'La petición ya está aceptada'], 200);
            }

            $peticion->estado = 'aceptada';
            $peticion->save();

            return response()->json($peticion, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Petición no encontrada'], 404);
        } catch (Exception $e) {
            //return response()->json(['error' => 'Error al cambiar el estado'], 500);
            return response()->json($e->getMessage(), 500);

        }
    }

    public function delete(Request $request, $id)
    {
        try {
            $peticion = Peticione::findOrFail($id);
            $this->authorize('delete', $peticion);
            $peticion->delete();
            return response()->json(['message' => 'Petición eliminada'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Petición no encontrada'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al eliminar la petición'], 500);
        }
    }

    public function fileUpload(Request $req, $peticione_id = null)
    {
        if ($req->hasFile('file')) {
            $file = $req->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();

            // Guarda el archivo en /public/peticiones
            $file->move(public_path('peticiones'), $filename);

            $fileModel = new File;
            $fileModel->peticione_id = $peticione_id;
            $fileModel->name = $filename;
            $fileModel->file_path = 'peticiones/' . $filename;
            $fileModel->save();

            return $fileModel;
        }

        return null;
    }

}
