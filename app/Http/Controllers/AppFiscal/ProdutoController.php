<?php

namespace App\Http\Controllers\AppFiscal;

use Illuminate\Http\Request;
use App\Produto;

class ProdutoController extends Controller
{
	public function all(){
		$produtos = Produto::get();
		foreach($produtos as $p){
			$p->categoria;
			$p->listaPreco;
			$p->quantidade_vendas = $p->somaVendas();
			
		}
		$ps = $produtos->SortByDesc('quantidade_vendas');
		$temp = [];
		foreach($ps as $s){
			array_push($temp, $s);
		}
		return response()->json($temp, 200);
	}

	public function salvar(Request $request){
		
		if($request->id > 0){
			$produto = Produto::find($request->id);

			$produto->nome = $request->nome;
			$produto->categoria_id = $request->categoria_id;
			$produto->cor = $request->cor;
			$produto->valor_venda = $request->valor_venda;


			$res = $produto->save();
		}else{
			$data = [
				'nome' => $request->nome,
				'categoria_id' => $request->categoria_id,
				'cor' => $request->cor ?? '',
				'valor_venda' => $request->valor_venda,
				'NCM' => $request->NCM,
				'CST_CSOSN' => $request->CST_CSOSN,
				'CST_PIS' => $request->CST_PIS,
				'CST_COFINS' => $request->CST_PIS,
				'CST_IPI' => $request->CST_IPI,
				'unidade_compra' => $request->unidade_compra,
				'unidade_venda' => $request->unidade_venda,
				'composto' => false,
				'codBarras' => $request->codBarras ?? 'SEM GTIN',
				'conversao_unitaria' => 1,
				'valor_livre' => $request->valor_livre ?? false,
				'perc_icms' => $request->perc_icms,
				'perc_pis' => $request->perc_pis,
				'perc_cofins' => $request->perc_cofins,
				'perc_ipi' => $request->perc_pis,
				'CFOP_saida_estadual' => $request->CFOP_saida_estadual,
				'CFOP_saida_inter_estadual' => $request->CFOP_saida_inter_estadual,
				'codigo_anp' => '',
				'descricao_anp' => '',
				'perc_iss' => 0,
				'cListServ' => '',
				'imagem' => '',
				'alerta_vencimento' => $request->alerta_vencimento ?? 0,
				'valor_compra' => $request->valor_compra,
			];
			$res = Produto::create($data);
		}

		return response()->json($res, 200);
	}

	public function delete(Request $request){
		$produto = Produto::find($request->id);
		$delete = $produto->delete();
		return response()->json($delete, 200);
	}

	public function dadosParaCadastro(){
		$data = [
			'unidades_medida' => Produto::unidadesMedida(),
			'listaCSTCSOSN' => $this->itetable(Produto::listaCSTCSOSN()),
			'listaCST_PIS_COFINS' => $this->itetable(Produto::listaCST_PIS_COFINS()),
			'listaCST_IPI' => $this->itetable(Produto::listaCST_IPI()),
			'lista_ANP' => Produto::lista_ANP()
		];
		return response()->json($data, 200);
	}

	private function itetable($array){
		$temp = [];
		foreach($array as $key => $a){
			$t = [
				'cod' => $key,
				'value' => $a
			];
			array_push($temp, $t);
		}
		return $temp;
	}
}