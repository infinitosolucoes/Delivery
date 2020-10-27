<?php

namespace App\Http\Controllers\AppFiscal;

use Illuminate\Http\Request;
use App\ConfigNota;
use App\VendaCaixa;
use App\Services\NFeService;
use NFePHP\DA\NFe\Danfce;

class NfceAppController extends Controller
{
	public function transmitir(Request $request){
		$vendaId = $request->id;

		$venda = VendaCaixa::
		where('id', $vendaId)
		->first();

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfe_service = new NFeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->UF,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		], 65);

		if($venda->estado == 'REJEITADO' || $venda->estado == 'DISPONIVEL'){
			header('Content-type: text/html; charset=UTF-8');

			$nfce = $nfe_service->gerarNFCe($vendaId);

			$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
			$signed = $nfe_service->sign($nfce['xml']);
			// file_put_contents($public.'xml_nfce/'.$venda->id.'.xml',$signed);
			$resultado = $nfe_service->transmitirNfce($signed, $nfce['chave']);

			if(substr($resultado, 0, 4) != 'Erro'){
				$venda->chave = $nfce['chave'];
				$venda->path_xml = $nfce['chave'] . '.xml';
				$venda->estado = 'APROVADO';

				$venda->NFcNumero = $nfce['nNf'];
				$venda->save();
				$this->imprimir($venda->id);
				$res = [
					'protocolo' => $resultado,
					'url' => getenv("PATH_URL") . '/' . $public.'pdf/DANFCE.pdf'
				];
				return response()->json($res, 200);

			}else{
				$venda->estado = 'REJEITADO';
				$venda->save();
			}
			return response()->json($resultado, 401);

		}else{
			return response()->json("erro", 403);
		}
	}

	public function imprimir($id){
		$venda = VendaCaixa::find($id);

		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';

		$xml = file_get_contents($public.'xml_nfce/'.$venda->chave.'.xml');
		$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents($public.'imgs/logo.jpg'));
		// $docxml = FilesFolders::readFile($xml);

		try {
			$danfce = new Danfce($xml);
			$danfce->monta($logo);
			$pdf = $danfce->render();

			header('Content-Type: application/pdf');
			file_put_contents($public.'pdf/DANFCE.pdf',$pdf);
			return response()->json($public.'pdf/DANFCE.pdf', 200);
		} catch (InvalidArgumentException $e) {
			return response()->json("erro", 401);
			echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
		}  
	}
}
