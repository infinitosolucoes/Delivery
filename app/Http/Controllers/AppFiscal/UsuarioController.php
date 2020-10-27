<?php

namespace App\Http\Controllers\AppFiscal;

use Illuminate\Http\Request;
use App\Usuario;

class UsuarioController extends Controller
{
	public function index(Request $request){
		$keyENV = getenv('KEY_APP');
		$login = $request->login;
		$senha = $request->senha;
		$key_app = $request->key_app;

		$usuario = Usuario::
		where('login', $login)
		->where('senha', md5($senha))
		->first();

		if($usuario == null) return response()->json(null, 401);

		if($keyENV != $key_app) return response()->json(null, 401);

		$credenciais = [
			'nome' => $usuario->nome,
			'token' => base64_encode($usuario->id . ';' . $usuario->login . ';' . $key_app),
			'id' => $usuario->id
		];

		return response()->json($credenciais, 200);
	}

}