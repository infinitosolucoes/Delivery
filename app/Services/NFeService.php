<?php
namespace App\Services;

use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use App\Venda;
use App\VendaCaixa;
use App\ConfigNota;
use App\Certificado;
use NFePHP\NFe\Complements;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\Legacy\FilesFolders;
use NFePHP\Common\Soap\SoapCurl;
use App\Tributacao;

error_reporting(E_ALL);
ini_set('display_errors', 'On');

class NFeService{

	private $config; 
	private $tools;

	public function __construct($config, $modelo){
		$certificado = Certificado::first();
		$this->config = $config;
		$this->tools = new Tools(json_encode($config), Certificate::readPfx($certificado->arquivo, $certificado->senha));
		$this->tools->model($modelo);
		
	}

	public function gerarNFe($idVenda){
		$venda = Venda::
		where('id', $idVenda)
		->first();

		$config = ConfigNota::first(); // iniciando os dados do emitente NF
		$tributacao = Tributacao::first(); // iniciando tributos

		$nfe = new Make();
		$stdInNFe = new \stdClass();
		$stdInNFe->versao = '4.00'; 
		$stdInNFe->Id = null; 
		$stdInNFe->pk_nItem = ''; 

		$infNFe = $nfe->taginfNFe($stdInNFe);

		$vendaLast = Venda::lastNF();
		$lastNumero = $vendaLast;
		
		$stdIde = new \stdClass();
		$stdIde->cUF = $config->cUF;
		$stdIde->cNF = rand(11111,99999);
		// $stdIde->natOp = $venda->natureza->natureza;
		$stdIde->natOp = $venda->natureza->natureza;

		// $stdIde->indPag = 1; //NÃO EXISTE MAIS NA VERSÃO 4.00 // forma de pagamento

		$stdIde->mod = 55;
		$stdIde->serie = $config->numero_serie_nfe;
		$stdIde->nNF = (int)$lastNumero+1;
		$stdIde->dhEmi = date("Y-m-d\TH:i:sP");
		$stdIde->dhSaiEnt = date("Y-m-d\TH:i:sP");
		$stdIde->tpNF = 1;
		$stdIde->idDest = $config->UF != $venda->cliente->cidade->uf ? 2 : 1;
		$stdIde->cMunFG = $config->codMun;
		$stdIde->tpImp = 1;
		$stdIde->tpEmis = 1;
		$stdIde->cDV = 0;
		$stdIde->tpAmb = $config->ambiente;
		$stdIde->finNFe = 1;
		$stdIde->indFinal = $venda->cliente->consumidor_final;
		$stdIde->indPres = 1;
		$stdIde->procEmi = '0';
		$stdIde->verProc = '2.0';
		// $stdIde->dhCont = null;
		// $stdIde->xJust = null;


		//
		$tagide = $nfe->tagide($stdIde);

		$stdEmit = new \stdClass();
		$stdEmit->xNome = $config->razao_social;
		$stdEmit->xFant = $config->nome_fantasia;

		$ie = str_replace(".", "", $config->ie);
		$ie = str_replace("/", "", $ie);
		$ie = str_replace("-", "", $ie);
		$stdEmit->IE = $ie;
		$stdEmit->CRT = $tributacao->regime == 0 ? 1 : 3;

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$stdEmit->CNPJ = $cnpj;
		// $stdEmit->IM = $ie;

		$emit = $nfe->tagemit($stdEmit);

		// ENDERECO EMITENTE
		$stdEnderEmit = new \stdClass();
		$stdEnderEmit->xLgr = $config->logradouro;
		$stdEnderEmit->nro = $config->numero;
		$stdEnderEmit->xCpl = "";
		
		$stdEnderEmit->xBairro = $config->bairro;
		$stdEnderEmit->cMun = $config->codMun;
		$stdEnderEmit->xMun = $config->municipio;
		$stdEnderEmit->UF = $config->UF;

		$cep = str_replace("-", "", $config->cep);
		$cep = str_replace(".", "", $cep);
		$stdEnderEmit->CEP = $cep;
		$stdEnderEmit->cPais = $config->codPais;
		$stdEnderEmit->xPais = $config->pais;

		$enderEmit = $nfe->tagenderEmit($stdEnderEmit);

		// DESTINATARIO
		$stdDest = new \stdClass();
		$stdDest->xNome = $venda->cliente->razao_social;

		if($venda->cliente->contribuinte){
			if($venda->cliente->ie_rg == 'ISENTO'){
				$stdDest->indIEDest = "2";
			}else{
				$stdDest->indIEDest = "1";
			}
			
		}else{
			$stdDest->indIEDest = "9";
		}


		$cnpj_cpf = str_replace(".", "", $venda->cliente->cpf_cnpj);
		$cnpj_cpf = str_replace("/", "", $cnpj_cpf);
		$cnpj_cpf = str_replace("-", "", $cnpj_cpf);

		if(strlen($cnpj_cpf) == 14){
			$stdDest->CNPJ = $cnpj_cpf;
			$ie = str_replace(".", "", $venda->cliente->ie_rg);
			$ie = str_replace("/", "", $ie);
			$ie = str_replace("-", "", $ie);
			$stdDest->IE = $ie;
		}
		else{
			$stdDest->CPF = $cnpj_cpf;
		} 

		$dest = $nfe->tagdest($stdDest);

		$stdEnderDest = new \stdClass();
		$stdEnderDest->xLgr = $venda->cliente->rua;
		$stdEnderDest->nro = $venda->cliente->numero;
		$stdEnderDest->xCpl = "";
		$stdEnderDest->xBairro = $venda->cliente->bairro;
		$stdEnderDest->cMun = $venda->cliente->cidade->codigo;
		$stdEnderDest->xMun = strtoupper($venda->cliente->cidade->nome);
		$stdEnderDest->UF = $venda->cliente->cidade->uf;

		$cep = str_replace("-", "", $venda->cliente->cep);
		$cep = str_replace(".", "", $cep);
		$stdEnderDest->CEP = $cep;
		$stdEnderDest->cPais = "1058";
		$stdEnderDest->xPais = "BRASIL";

		$enderDest = $nfe->tagenderDest($stdEnderDest);

		$somaProdutos = 0;
		$somaICMS = 0;
		$somaIPI = 0;
		//PRODUTOS
		$itemCont = 0;

		$totalItens = count($venda->itens);
		$somaFrete = 0;
		$somaDesconto = 0;
		$somaISS = 0;
		$somaServico = 0;
		foreach($venda->itens as $i){
			$itemCont++;

			$stdProd = new \stdClass();
			$stdProd->item = $itemCont;
			$stdProd->cEAN = $i->produto->codBarras;
			$stdProd->cEANTrib = $i->produto->codBarras;
			$stdProd->cProd = $i->produto->id;
			$stdProd->xProd = $i->produto->nome;
			$ncm = $i->produto->NCM;
			$ncm = str_replace(".", "", $ncm);

			if($i->produto->perc_iss > 0){
				$stdProd->NCM = '00';
			}else{
				$stdProd->NCM = $ncm;
			}
			
			$stdProd->CFOP = $config->UF != $venda->cliente->cidade->uf ?
			$i->produto->CFOP_saida_inter_estadual : $i->produto->CFOP_saida_estadual;


			$cest = $i->produto->CEST;
			$cest = str_replace(".", "", $cest);
			$stdProd->CEST = $cest;

			$stdProd->uCom = $i->produto->unidade_venda;
			$stdProd->qCom = $i->quantidade;
			$stdProd->vUnCom = $this->format($i->valor);
			$stdProd->vProd = $this->format(($i->quantidade * $i->valor));
			$stdProd->uTrib = $i->produto->unidade_venda;
			$stdProd->qTrib = $i->quantidade;
			$stdProd->vUnTrib = $this->format($i->valor);
			$stdProd->indTot = $i->produto->perc_iss > 0 ? 0 : 1;
			$somaProdutos += ($i->quantidade * $i->valor);

			$vDesc = 0;
			if($venda->desconto > 0){
				if($itemCont < sizeof($venda->itens)){
					$stdProd->vDesc = $this->format($venda->desconto/$totalItens);
					$somaDesconto += $vDesc = $venda->desconto/$totalItens;
				}else{
					$stdProd->vDesc = $somaDesconto = $vDesc = $venda->desconto - $somaDesconto;
				}
			}

			if($venda->frete){
				if($venda->frete->valor > 0){
					$somaFrete += $vFt = $venda->frete->valor/$totalItens;
					$stdProd->vFrete = $this->format($vFt);
				}
			}

			$prod = $nfe->tagprod($stdProd);

		//TAG IMPOSTO

			$stdImposto = new \stdClass();
			$stdImposto->item = $itemCont;
			if($i->produto->perc_iss > 0){
				$stdImposto->vTotTrib = 0.00;
			}

			$imposto = $nfe->tagimposto($stdImposto);

			// ICMS
			if($i->produto->perc_iss == 0){
				// regime normal
				if($tributacao->regime == 1){ 

				//$venda->produto->CST  CST

					$stdICMS = new \stdClass();
					$stdICMS->item = $itemCont; 
					$stdICMS->orig = 0;
					$stdICMS->CST = $i->produto->CST_CSOSN;
					$stdICMS->modBC = 0;
					$stdICMS->vBC = $this->format($i->valor * $i->quantidade);
					$stdICMS->pICMS = $this->format($i->produto->perc_icms);
					$stdICMS->vICMS = $stdICMS->vBC * ($stdICMS->pICMS/100);

					$somaICMS += (($i->valor * $i->quantidade) 
						* ($stdICMS->pICMS/100));
					$ICMS = $nfe->tagICMS($stdICMS);
					// regime simples
				}else{ 

				//$venda->produto->CST CSOSN

					$stdICMS = new \stdClass();

					$stdICMS->item = $itemCont; 
					$stdICMS->orig = 0;
					$stdICMS->CSOSN = $i->produto->CST_CSOSN;

					if($i->produto->CST_CSOSN == '500'){
						$stdICMS->vBCSTRet = 0.00;
						$stdICMS->pST = 0.00;
						$stdICMS->vICMSSTRet = 0.00;
					}

					$stdICMS->pCredSN = $this->format($i->produto->perc_icms);
					$stdICMS->vCredICMSSN = $this->format($i->produto->perc_icms);
					$ICMS = $nfe->tagICMSSN($stdICMS);

					$somaICMS = 0;
				}
			} 

			else
			{
				$valorIss = ($i->valor * $i->quantidade) - $vDesc;
				$somaServico += $valorIss;
				$valorIss = $valorIss * ($i->produto->perc_iss/100);
				$somaISS += $valorIss;


				$std = new \stdClass();
				$std->item = $itemCont; 
				$std->vBC = $stdProd->vProd;
				$std->vAliq = $i->produto->perc_iss;
				$std->vISSQN = $this->format($valorIss);
				$std->cMunFG = $config->codMun;
				$std->cListServ = $i->produto->cListServ;
				$std->indISS = 1;
				$std->indIncentivo = 1;

				$nfe->tagISSQN($std);
			}

				//PIS
			$stdPIS = new \stdClass();
			$stdPIS->item = $itemCont; 
			$stdPIS->CST = $i->produto->CST_PIS;
			$stdPIS->vBC = $this->format($i->produto->perc_pis) > 0 ? $stdProd->vProd : 0.00;
			$stdPIS->pPIS = $this->format($i->produto->perc_pis);
			$stdPIS->vPIS = $this->format(($stdProd->vProd * $i->quantidade) * 
				($i->produto->perc_pis/100));
			$PIS = $nfe->tagPIS($stdPIS);

				//COFINS
			$stdCOFINS = new \stdClass();
			$stdCOFINS->item = $itemCont; 
			$stdCOFINS->CST = $i->produto->CST_COFINS;
			$stdCOFINS->vBC = $this->format($i->produto->perc_cofins) > 0 ? $stdProd->vProd : 0.00;
			$stdCOFINS->pCOFINS = $this->format($i->produto->perc_cofins);
			$stdCOFINS->vCOFINS = $this->format(($stdProd->vProd * $i->quantidade) * 
				($i->produto->perc_cofins/100));
			$COFINS = $nfe->tagCOFINS($stdCOFINS);


				//IPI

			$std = new \stdClass();
			$std->item = $itemCont; 
				//999 – para tributação normal IPI
			$std->cEnq = '999'; 
			$std->CST = $i->produto->CST_IPI;
			$std->vBC = $this->format($i->produto->perc_ipi) > 0 ? $stdProd->vProd : 0.00;
			$std->pIPI = $this->format($i->produto->perc_ipi);
			$somaIPI += $std->vIPI = $stdProd->vProd * $this->format(($i->produto->perc_ipi/100));

			$nfe->tagIPI($std);
			


			//TAG ANP

			if(strlen($i->produto->descricao_anp) > 5){
				$stdComb = new \stdClass();
				$stdComb->item = 1; 
				$stdComb->cProdANP = $i->produto->codigo_anp;
				$stdComb->descANP = $i->produto->descricao_anp; 
				$stdComb->UFCons = $venda->cliente->cidade->uf;

				$nfe->tagcomb($stdComb);
			}

			
		}


		$stdICMSTot = new \stdClass();
		$stdICMSTot->vProd = 0;
		$stdICMSTot->vBC = $tributacao->regime == 1 ? $this->format($somaProdutos) : 0.00;
		$stdICMSTot->vICMS = $this->format($somaICMS);
		$stdICMSTot->vICMSDeson = 0.00;
		$stdICMSTot->vBCST = 0.00;
		$stdICMSTot->vST = 0.00;

		if($venda->frete) $stdICMSTot->vFrete = $this->format($venda->frete->valor);
		else $stdICMSTot->vFrete = 0.00;

		$stdICMSTot->vSeg = 0.00;
		$stdICMSTot->vDesc = $this->format($venda->desconto);
		$stdICMSTot->vII = 0.00;
		$stdICMSTot->vIPI = 0.00;
		$stdICMSTot->vPIS = 0.00;
		$stdICMSTot->vCOFINS = 0.00;
		$stdICMSTot->vOutro = 0.00;
		
		if($venda->frete){
			$stdICMSTot->vNF = 
			$this->format(($somaProdutos+$venda->frete->valor+$somaIPI)-$venda->desconto);
		} 
		else $stdICMSTot->vNF = $this->format($somaProdutos+$somaIPI-$venda->desconto);

		$stdICMSTot->vTotTrib = 0.00;
		$ICMSTot = $nfe->tagICMSTot($stdICMSTot);

		//inicio totalizao issqn

		if($somaISS > 0){
			$std = new \stdClass();
			$std->vServ = $this->format($somaServico + $venda->desconto);
			$std->vBC = $this->format($somaServico);
			$std->vISS = $this->format($somaISS);
			$std->dCompet = date('Y-m-d');

			$std->cRegTrib = 6;

			$nfe->tagISSQNTot($std);
		}

		//fim totalizao issqn



		$stdTransp = new \stdClass();
		$stdTransp->modFrete = $venda->frete->tipo ?? '9';

		$transp = $nfe->tagtransp($stdTransp);


		if($venda->transportadora){
			$std = new \stdClass();
			$std->xNome = $venda->transportadora->razao_social;

			$std->xEnder = $venda->transportadora->logradouro;
			$std->xMun = strtoupper($venda->transportadora->cidade->nome);
			$std->UF = $venda->transportadora->cidade->uf;


			$cnpj_cpf = $venda->transportadora->cnpj_cpf;
			$cnpj_cpf = str_replace(".", "", $venda->transportadora->cnpj_cpf);
			$cnpj_cpf = str_replace("/", "", $cnpj_cpf);
			$cnpj_cpf = str_replace("-", "", $cnpj_cpf);

			if(strlen($cnpj_cpf) == 14) $std->CNPJ = $cnpj_cpf;
			else $std->CPF = $cnpj_cpf;

			$nfe->tagtransporta($std);
		}


		if($venda->frete != null){

			$std = new \stdClass();


			$placa = str_replace("-", "", $venda->frete->placa);
			$std->placa = strtoupper($placa);
			$std->UF = $venda->frete->uf;

			if($config->UF == $venda->cliente->cidade->uf){
				$nfe->tagveicTransp($std);
			}


			if($venda->frete->qtdVolumes > 0 && $venda->frete->peso_liquido > 0
				&& $venda->frete->peso_bruto > 0){
				$stdVol = new \stdClass();
				$stdVol->item = 1;
				$stdVol->qVol = $venda->frete->qtdVolumes;
				$stdVol->esp = $venda->frete->especie;

				$stdVol->nVol = $venda->frete->numeracaoVolumes;
				$stdVol->pesoL = $venda->frete->peso_liquido;
				$stdVol->pesoB = $venda->frete->peso_bruto;
				$vol = $nfe->tagvol($stdVol);
			}
		}



		$std = new \stdClass();
		$std->CNPJ = getenv('RESP_CNPJ'); //CNPJ da pessoa jurídica responsável pelo sistema utilizado na emissão do documento fiscal eletrônico
		$std->xContato= getenv('RESP_NOME'); //Nome da pessoa a ser contatada
		$std->email = getenv('RESP_EMAIL'); //E-mail da pessoa jurídica a ser contatada
		$std->fone = getenv('RESP_FONE'); //Telefone da pessoa jurídica/física a ser contatada
		$nfe->taginfRespTec($std);


	//Fatura
		if($somaISS == 0 && $venda->natureza->CFOP_saida_estadual != '5915' && $venda->natureza->CFOP_saida_inter_estadual != '6915'){
			$stdFat = new \stdClass();
			$stdFat->nFat = (int)$lastNumero+1;
			$stdFat->vOrig = $this->format($somaProdutos);
			$stdFat->vDesc = $this->format($venda->desconto);
			$stdFat->vLiq = $this->format($somaProdutos-$venda->desconto);

			$fatura = $nfe->tagfat($stdFat);
		}

	//Duplicata
		if($somaISS == 0 && $venda->natureza->CFOP_saida_estadual != '5915' && $venda->natureza->CFOP_saida_inter_estadual != '6915'){
			if(count($venda->duplicatas) > 0){
				$contFatura = 1;
				foreach($venda->duplicatas as $ft){
					$stdDup = new \stdClass();
					$stdDup->nDup = "00".$contFatura;
					$stdDup->dVenc = substr($ft->data_vencimento, 0, 10);
					$stdDup->vDup = $this->format($ft->valor_integral);

					$nfe->tagdup($stdDup);
					$contFatura++;
				}
			}else{
				if( $venda->forma_pagamento != 'a_vista'){
					$stdDup = new \stdClass();
					$stdDup->nDup = '001';
					$stdDup->dVenc = Date('Y-m-d');
					$stdDup->vDup =  $this->format($somaProdutos-$venda->desconto);

					$nfe->tagdup($stdDup);
				}
			}
		}



		$stdPag = new \stdClass();
		$pag = $nfe->tagpag($stdPag);

		$stdDetPag = new \stdClass();


		$stdDetPag->tPag = $venda->tipo_pagamento;
		$stdDetPag->vPag = $venda->tipo_pagamento != '90' ? $this->format($stdProd->vProd - $venda->desconto) : 0.00; 

		if($venda->tipo_pagamento == '03' || $venda->tipo_pagamento == '04'){
			$stdDetPag->CNPJ = '12345678901234';
			$stdDetPag->tBand = '01';
			$stdDetPag->cAut = '3333333';
			$stdDetPag->tpIntegra = 1;
		}
		$stdDetPag->indPag = $venda->forma_pagamento == 'a_vista' ?  0 : 1; 

		$detPag = $nfe->tagdetPag($stdDetPag);



		$stdInfoAdic = new \stdClass();
		$stdInfoAdic->infCpl = $venda->observacao;

		$infoAdic = $nfe->taginfAdic($stdInfoAdic);



		$std = new \stdClass();
		$std->CNPJ = getenv('RESP_CNPJ'); //CNPJ da pessoa jurídica responsável pelo sistema utilizado na emissão do documento fiscal eletrônico
		$std->xContato= getenv('RESP_NOME'); //Nome da pessoa a ser contatada
		$std->email = getenv('RESP_EMAIL'); //E-mail da pessoa jurídica a ser contatada
		$std->fone = getenv('RESP_FONE'); //Telefone da pessoa jurídica/física a ser contatada
		$nfe->taginfRespTec($std);
		
		if(getenv("AUTXML")){
			$std = new \stdClass();
			$std->CNPJ = getenv("AUTXML"); 
			$std->CPF = null;
			$nfe->tagautXML($std);
		}

		if($nfe->montaNFe()){
			$arr = [
				'chave' => $nfe->getChave(),
				'xml' => $nfe->getXML(),
				'nNf' => $stdIde->nNF
			];
			return $arr;
		} else {
			throw new Exception("Erro ao gerar NFe");
		}

	}

	public function format($number, $dec = 2){
		return number_format((float) $number, $dec, ".", "");
	}

	public function consultaCadastro($cnpj, $uf){
		try {

			$iest = '';
			$cpf = '';
			$response = $this->tools->sefazCadastro($uf, $cnpj, $iest, $cpf);

			$stdCl = new Standardize($response);

			$std = $stdCl->toStd();

			$arr = $stdCl->toArray();

			$json = $stdCl->toJson();

			echo $json;

		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function consultaChave($chave){
		$response = $this->tools->sefazConsultaChave($chave);

		$stdCl = new Standardize($response);
		$arr = $stdCl->toArray();
		return $arr;
	}

	public function consultar($vendaId){
		try {
			$venda = Venda::
			where('id', $vendaId)
			->first();
			$this->tools->model('55');

			$chave = $venda->chave;
			$response = $this->tools->sefazConsultaChave($chave);

			$stdCl = new Standardize($response);
			$arr = $stdCl->toArray();

			// $arr = json_decode($json);
			return json_encode($arr);

		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function consultarNFCe($venda){
		try {
			
			$this->tools->model('65');

			$chave = $venda->chave;
			$response = $this->tools->sefazConsultaChave($chave);

			$stdCl = new Standardize($response);
			$arr = $stdCl->toArray();

			// $arr = json_decode($json);
			return json_encode($arr);

		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function inutilizar($nInicio, $nFinal, $justificativa){
		try{

			$nSerie = $config->numero_serie_nfe;
			$nIni = $nInicio;
			$nFin = $nFinal;
			$xJust = $justificativa;
			$response = $this->tools->sefazInutiliza($nSerie, $nIni, $nFin, $xJust);

			$stdCl = new Standardize($response);
			$std = $stdCl->toStd();
			$arr = $stdCl->toArray();
			$json = $stdCl->toJson();

			return $arr;

		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function cancelar($vendaId, $justificativa){
		try {
			$venda = Venda::
			where('id', $vendaId)
			->first();
				// $this->tools->model('55');

			$chave = $venda->chave;
			$response = $this->tools->sefazConsultaChave($chave);
			$stdCl = new Standardize($response);
			$arr = $stdCl->toArray();
			sleep(1);
				// return $arr;
			$xJust = $justificativa;


			$nProt = $arr['protNFe']['infProt']['nProt'];

			$response = $this->tools->sefazCancela($chave, $xJust, $nProt);
			sleep(2);
			$stdCl = new Standardize($response);
			$std = $stdCl->toStd();
			$arr = $stdCl->toArray();
			$json = $stdCl->toJson();

			if ($std->cStat != 128) {
        //TRATAR
			} else {
				$cStat = $std->retEvento->infEvento->cStat;
				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				if ($cStat == '101' || $cStat == '135' || $cStat == '155' ) {
            //SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
					$xml = Complements::toAuthorize($this->tools->lastRequest, $response);
					file_put_contents($public.'xml_nfe_cancelada/'.$chave.'.xml',$xml);

					return $json;
				} else {
					
					return ['erro' => true, 'data' => $arr, 'status' => 402];	
				}
			}    
		} catch (\Exception $e) {
			echo $e->getMessage();
    //TRATAR
		}
	}

	public function cartaCorrecao($id, $correcao){
		try {

			$venda = Venda::
			where('id', $id)
			->first();

			$chave = $venda->chave;
			$xCorrecao = $correcao;
			$nSeqEvento = $venda->sequencia_cce+1;
			$response = $this->tools->sefazCCe($chave, $xCorrecao, $nSeqEvento);
			sleep(2);

			$stdCl = new Standardize($response);

			$std = $stdCl->toStd();

			$arr = $stdCl->toArray();

			$json = $stdCl->toJson();

			if ($std->cStat != 128) {
        //TRATAR
			} else {
				$cStat = $std->retEvento->infEvento->cStat;
				if ($cStat == '135' || $cStat == '136') {
					$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
            //SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
					$xml = Complements::toAuthorize($this->tools->lastRequest, $response);
					file_put_contents($public.'xml_nfe_correcao/'.$chave.'.xml',$xml);

					$venda->sequencia_cce = $venda->sequencia_cce + 1;
					$venda->save();
					return $json;

				} else {
            //houve alguma falha no evento 
					return $json;
            //TRATAR
				}
			}    
		} catch (\Exception $e) {
			return $e->getMessage();
		}
	}

///***************** NFCE**********

	public function gerarNFCe($idVenda){
		$venda = VendaCaixa::
		where('id', $idVenda)
		->first();

		$config = ConfigNota::first();
		$tributacao = Tributacao::first(); 

		$nfe = new Make();
		$stdInNFe = new \stdClass();
		$stdInNFe->versao = '4.00'; //versão do layout
		$stdInNFe->Id = null; //se o Id de 44 digitos não for passado será gerado automaticamente
		$stdInNFe->pk_nItem = ''; //deixe essa variavel sempre como NULL

		$infNFe = $nfe->taginfNFe($stdInNFe);

		//IDE
		$stdIde = new \stdClass();
		$stdIde->cUF = $config->cUF;
		$stdIde->cNF = rand(11111111, 99999999);
		$stdIde->natOp = $config->natureza->natureza;

		// $stdIde->indPag = 1; //NÃO EXISTE MAIS NA VERSÃO 4.00 // forma de pagamento

		$vendaLast = VendaCaixa::lastNFCe();
		$lastNumero = $vendaLast;

		$stdIde->mod = 65;
		$stdIde->serie = $config->numero_serie_nfce;
		$stdIde->nNF = (int)$lastNumero+1; 
		$stdIde->dhEmi = date("Y-m-d\TH:i:sP");
		$stdIde->dhSaiEnt = date("Y-m-d\TH:i:sP");
		$stdIde->tpNF = 1;
		$stdIde->idDest = 1;
		$stdIde->cMunFG = $config->codMun;
		$stdIde->tpImp = 4;
		$stdIde->tpEmis = 1;
		$stdIde->cDV = 0;
		$stdIde->tpAmb = $config->ambiente;
		$stdIde->finNFe = 1;
		$stdIde->indFinal = 1;
		$stdIde->indPres = 1;
		$stdIde->procEmi = '0';
		$stdIde->verProc = '2.0';
		//
		$tagide = $nfe->tagide($stdIde);

		$stdEmit = new \stdClass();
		$stdEmit->xNome = $config->razao_social;
		$stdEmit->xFant = $config->nome_fantasia;

		$ie = str_replace(".", "", $config->ie);
		$ie = str_replace("/", "", $ie);
		$ie = str_replace("-", "", $ie);
		$stdEmit->IE = $ie;
		$stdEmit->CRT = $tributacao->regime == 0 ? 1 : 3;

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$stdEmit->CNPJ = $cnpj; 

		$emit = $nfe->tagemit($stdEmit);

		// ENDERECO EMITENTE
		$stdEnderEmit = new \stdClass();
		$stdEnderEmit->xLgr = $config->logradouro;
		$stdEnderEmit->nro = $config->numero;
		$stdEnderEmit->xCpl = "";
		$stdEnderEmit->xBairro = $config->bairro;
		$stdEnderEmit->cMun = $config->codMun;
		$stdEnderEmit->xMun = $config->municipio;
		$stdEnderEmit->UF = $config->UF;

		$cep = str_replace("-", "", $config->cep);
		$stdEnderEmit->CEP = $cep;
		$stdEnderEmit->cPais = $config->codPais;
		$stdEnderEmit->xPais = $config->pais;

		$fone = str_replace(" ", "", $config->fone);
		$fone = str_replace("-", "", $fone);
		$stdEnderEmit->fone = $fone;

		$enderEmit = $nfe->tagenderEmit($stdEnderEmit);

		// DESTINATARIO


		if($venda->cliente_id != null || $venda->cpf != null){
			$stdDest = new \stdClass();
			if($venda->cliente_id != null){
				$stdDest->xNome = $venda->cliente->razao_social;
				$stdDest->indIEDest = "1";

				$cnpj_cpf = str_replace(".", "", $venda->cliente->cpf_cnpj);
				$cnpj_cpf = str_replace("/", "", $cnpj_cpf);
				$cnpj_cpf = str_replace("-", "", $cnpj_cpf);

				if(strlen($cnpj_cpf) == 14) $stdDest->CNPJ = $cnpj_cpf;
				else $stdDest->CPF = $cnpj_cpf;

				$dest = $nfe->tagdest($stdDest);

				$stdEnderDest = new \stdClass();
				$stdEnderDest->xLgr = $venda->cliente->rua;
				$stdEnderDest->nro = $venda->cliente->numero;
				$stdEnderDest->xCpl = "";
				$stdEnderDest->xBairro = $venda->cliente->bairro;
				$stdEnderDest->cMun = $venda->cliente->cidade->codigo;
				$stdEnderDest->xMun = strtoupper($venda->cliente->cidade->nome);
				$stdEnderDest->UF = $venda->cliente->cidade->uf;

				$cep = str_replace("-", "", $venda->cliente->cep);
				$stdEnderDest->CEP = $cep;
				$stdEnderDest->cPais = "1058";
				$stdEnderDest->xPais = "BRASIL";
				$enderDest = $nfe->tagenderDest($stdEnderDest);

			}
			if($venda->cpf != null){

				$cpf = str_replace(".", "", $venda->cpf);
				$cpf = str_replace("/", "", $cpf);
				$cpf = str_replace("-", "", $cpf);
				$cpf = str_replace(" ", "", $cpf);

				if($venda->nome) $stdDest->xNome = $venda->nome;
				$stdDest->indIEDest = "9";
				$stdDest->CPF = $cpf;
				$dest = $nfe->tagdest($stdDest);
			}

		}


		$somaProdutos = 0;
		$somaICMS = 0;
		//PRODUTOS
		$itemCont = 0;
		$totalItens = count($venda->itens);
		$somaAcrescimo = 0;
		foreach($venda->itens as $i){
			$itemCont++;

			$stdProd = new \stdClass();
			$stdProd->item = $itemCont;
			$stdProd->cEAN = $i->produto->codBarras;
			$stdProd->cEANTrib = $i->produto->codBarras;
			$stdProd->cProd = $i->produto->id;
			$stdProd->xProd = $i->produto->nome;

			$ncm = $i->produto->NCM;
			$ncm = str_replace(".", "", $ncm);
			$stdProd->NCM = $ncm;

			$stdProd->CFOP = $i->produto->CFOP_saida_estadual;
			$cest = $i->produto->CEST;
			$cest = str_replace(".", "", $cest);
			$stdProd->CEST = $cest;
			$stdProd->uCom = $i->produto->unidade_venda;
			$stdProd->qCom = $i->quantidade;
			$stdProd->vUnCom = $this->format($i->valor);
			$stdProd->vProd = $this->format($i->quantidade * $i->valor);
			$stdProd->uTrib = $i->produto->unidade_venda;
			$stdProd->qTrib = $i->quantidade;
			$stdProd->vUnTrib = $this->format($i->valor);
			$stdProd->indTot = 1;


			//calculo media prod

			if($venda->acrescimo > 0){
				if($itemCont < sizeof($venda->itens)){
					$totalVenda = $venda->valor_total;

					$media = (((($stdProd->vProd-$totalVenda)/$totalVenda))*100);
					$media = 100 - ($media * -1);

					$tempAcrescimo = ($venda->acrescimo*$media)/100;
					$somaAcrescimo+=$tempAcrescimo;

					$stdProd->vOutro = $this->format($tempAcrescimo);
				}else{
					$stdProd->vOutro = $this->format($venda->acrescimo - $somaAcrescimo);
				}
			}
			// fim calculo
			

			if($venda->desconto > 0){
				$stdProd->vDesc = $this->format($venda->desconto/$totalItens);
			}

			$somaProdutos += $i->quantidade * $i->valor;


			$prod = $nfe->tagprod($stdProd);

			$tributacao = Tributacao::first();

			$stdImposto = new \stdClass();
			$stdImposto->item = $itemCont;

			$imposto = $nfe->tagimposto($stdImposto);

			if($tributacao->regime == 1){ // regime normal

				$stdICMS = new \stdClass();
				$stdICMS->item = $itemCont; 
				$stdICMS->orig = 0;
				$stdICMS->CST = $i->produto->CST_CSOSN;
				$stdICMS->modBC = 0;
				$stdICMS->vBC = $this->format($i->valor * $i->quantidade);
				$stdICMS->pICMS = $this->format($i->produto->perc_icms);
				$stdICMS->vICMS = $stdICMS->vBC * ($stdICMS->pICMS/100);

				$somaICMS += $stdICMS->vICMS;
				$ICMS = $nfe->tagICMS($stdICMS);

			}else{ // regime simples
				
				$stdICMS = new \stdClass();
				
				$stdICMS->item = $itemCont; 
				$stdICMS->orig = 0;
				$stdICMS->CSOSN = $i->produto->CST_CSOSN;
				$stdICMS->pCredSN = $this->format($i->produto->perc_icms);
				$stdICMS->vCredICMSSN = $this->format($i->produto->perc_icms);
				$ICMS = $nfe->tagICMSSN($stdICMS);

				$somaICMS = 0;
			}



			$stdPIS = new \stdClass();
			$stdPIS->item = $itemCont; 
			$stdPIS->CST = $i->produto->CST_PIS;
			$stdPIS->vBC = $this->format($i->produto->perc_pis) > 0 ? $stdProd->vProd : 0.00;
			$stdPIS->pPIS = $this->format($i->produto->perc_pis);
			$stdPIS->vPIS = $this->format(($stdProd->vProd * $i->quantidade) * ($i->produto->perc_pis/100));
			$PIS = $nfe->tagPIS($stdPIS);

		//COFINS
			$stdCOFINS = new \stdClass();
			$stdCOFINS->item = $itemCont; 
			$stdCOFINS->CST = $i->produto->CST_COFINS;
			$stdCOFINS->vBC = $this->format($i->produto->perc_cofins) > 0 ? $stdProd->vProd : 0.00;
			$stdCOFINS->pCOFINS = $this->format($i->produto->perc_cofins);
			$stdCOFINS->vCOFINS = $this->format(($stdProd->vProd * $i->quantidade) * 
				($i->produto->perc_cofins/100));
			$COFINS = $nfe->tagCOFINS($stdCOFINS);

			if(strlen($i->produto->descricao_anp) > 5){
				$stdComb = new \stdClass();
				$stdComb->item = 1; 
				$stdComb->cProdANP = $i->produto->codigo_anp;
				$stdComb->descANP = $i->produto->descricao_anp; 
				$stdComb->UFCons = $venda->cliente->cidade->uf;

				$nfe->tagcomb($stdComb);
			}
		}

		//ICMS TOTAL
		$stdICMSTot = new \stdClass();
		$stdICMSTot->vBC = $tributacao->regime == 1 ? $this->format($somaProdutos) : 0.00;
		$stdICMSTot->vICMS = $this->format($somaICMS);
		$stdICMSTot->vICMSDeson = 0.00;
		$stdICMSTot->vBCST = 0.00;
		$stdICMSTot->vST = 0.00;
		$stdICMSTot->vProd = $this->format($somaProdutos);
		
		$stdICMSTot->vFrete = 0.00;

		$stdICMSTot->vSeg = 0.00;
		$stdICMSTot->vDesc = $this->format($venda->desconto);
		$stdICMSTot->vII = 0.00;
		$stdICMSTot->vIPI = 0.00;
		$stdICMSTot->vPIS = 0.00;
		$stdICMSTot->vCOFINS = 0.00;
		$stdICMSTot->vOutro = 0.00;
		$stdICMSTot->vNF = $this->format($venda->valor_total + $venda->acrescimo);
		$stdICMSTot->vTotTrib = 0.00;
		$ICMSTot = $nfe->tagICMSTot($stdICMSTot);

		//TRANSPORTADORA

		$stdTransp = new \stdClass();
		$stdTransp->modFrete = 9;

		$transp = $nfe->tagtransp($stdTransp);

		
		$stdPag = new \stdClass();

		$stdPag->vTroco = $this->format($venda->troco); 


		$pag = $nfe->tagpag($stdPag);

		//Resp Tecnico
		$stdResp = new \stdClass();
		$stdResp->CNPJ = getenv('RESP_CNPJ'); 
		$stdResp->xContato= getenv('RESP_NOME');
		$stdResp->email = getenv('RESP_EMAIL'); 
		$stdResp->fone = getenv('RESP_FONE'); 

		$nfe->taginfRespTec($stdResp);

		//DETALHE PAGAMENTO

		$stdDetPag = new \stdClass();
		$stdDetPag->indPag = 0;

		$stdDetPag->tPag = $venda->tipo_pagamento; 
		$stdDetPag->vPag = $this->format($venda->dinheiro_recebido); //Obs: deve ser informado o valor pago pelo cliente

		if($venda->tipo_pagamento == '03' || $venda->tipo_pagamento == '04'){
			$stdDetPag->CNPJ = '12345678901234';
			$stdDetPag->tBand = '01';
			$stdDetPag->cAut = '3333333';
			$stdDetPag->tpIntegra = 1;
		}
		
		// $std->tpIntegra = 1; //incluso na NT 2015/002
		// $std->indPag = '0'; //0= Pagamento à Vista 1= Pagamento à Prazo

		$detPag = $nfe->tagdetPag($stdDetPag);

		//INFO ADICIONAL
		// $stdInfoAdic = new \stdClass();
		// $stdInfoAdic->infAdFisco = 'informacoes para o fisco';
		// $stdInfoAdic->infCpl = 'informacoes complementares';

		// $infoAdic = $nfe->taginfAdic($stdInfoAdic);
		if($nfe->monta()){

			$arr = [
				'chave' => $nfe->getChave(),
				'xml' => $nfe->getXML(),
				'nNf' => $stdIde->nNF,
				'modelo' => $nfe->getModelo()
			];
			return $arr;
		} else {
			throw new Exception("Erro ao gerar NFce");
		}

	}

	public function sign($xml){
		return $this->tools->signNFe($xml);
	}

	public function transmitir($signXml, $chave){
		try{
			$idLote = str_pad(100, 15, '0', STR_PAD_LEFT);
			$resp = $this->tools->sefazEnviaLote([$signXml], $idLote);

			$st = new Standardize();
			$std = $st->toStd($resp);
			sleep(2);
			if ($std->cStat != 103) {

				return "[$std->cStat] - $std->xMotivo";
			}
			sleep(3);
			$recibo = $std->infRec->nRec; 
			
			$protocolo = $this->tools->sefazConsultaRecibo($recibo);
			sleep(4);
			//return $protocolo;
			$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
			try {
				$xml = Complements::toAuthorize($signXml, $protocolo);
				header('Content-type: text/xml; charset=UTF-8');
				file_put_contents($public.'xml_nfe/'.$chave.'.xml',$xml);
				return $recibo;
				// $this->printDanfe($xml);
			} catch (\Exception $e) {
				return "Erro: " . $st->toJson($protocolo);
			}

		} catch(\Exception $e){
			return "Erro: ".$e->getMessage() ;
		}

	}	

	public function transmitirNfce($signXml, $chave){
		try{
			$idLote = str_pad(100, 15, '0', STR_PAD_LEFT);
			$resp = $this->tools->sefazEnviaLote([$signXml], $idLote);
			sleep(1);
			$st = new Standardize();
			$std = $st->toStd($resp);

			if ($std->cStat != 103) {

				return "[$std->cStat] - $std->xMotivo";
			}
			sleep(1);
			$recibo = $std->infRec->nRec; 
			$protocolo = $this->tools->sefazConsultaRecibo($recibo);
			sleep(1);
			// return $protocolo;

			$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
			try {
				$xml = Complements::toAuthorize($signXml, $protocolo);
				header('Content-type: text/xml; charset=UTF-8');
				file_put_contents($public.'xml_nfce/'.$chave.'.xml',$xml);
				return $recibo;
				// $this->printDanfe($xml);
			} catch (\Exception $e) {
				return "Erro: " . $st->toJson($protocolo);
			}

		} catch(\Exception $e){
			return "Erro: ".$e->getMessage() ;
		}

	}	

	public function cancelarNFCe($vendaId, $justificativa){
		try {
			$venda = VendaCaixa::
			where('id', $vendaId)
			->first();

			$chave = $venda->chave;
			$response = $this->tools->sefazConsultaChave($chave);
			sleep(1);
			$stdCl = new Standardize($response);
			$arr = $stdCl->toArray();
				// return $arr;
			$xJust = $justificativa;


			$nProt = $arr['protNFe']['infProt']['nProt'];
			sleep(1);

			$response = $this->tools->sefazCancela($chave, $xJust, $nProt);

			$stdCl = new Standardize($response);
			$std = $stdCl->toStd();
			$arr = $stdCl->toArray();
			$json = $stdCl->toJson();

			$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
			if ($std->cStat != 128) {

			} else {
				$cStat = $std->retEvento->infEvento->cStat;
				if ($cStat == '101' || $cStat == '135' || $cStat == '155' ) {
            //SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
					$xml = Complements::toAuthorize($this->tools->lastRequest, $response);
					file_put_contents($public.'xml_nfce_cancelada/'.$chave.'.xml',$xml);

					return $arr;
				} else {
					return $arr;	
				}
			}    
		} catch (\Exception $e) {
			return 
			[
				'mensagem' => $e->getMessage(),
				'erro' => true
			];
    //TRATAR
		}
	}


	public function simularOrcamento($venda){
		

		$config = ConfigNota::first(); // iniciando os dados do emitente NF
		$tributacao = Tributacao::first(); // iniciando tributos

		$nfe = new Make();
		$stdInNFe = new \stdClass();
		$stdInNFe->versao = '4.00'; 
		$stdInNFe->Id = null; 
		$stdInNFe->pk_nItem = ''; 

		$infNFe = $nfe->taginfNFe($stdInNFe);

		$vendaLast = Venda::lastNF();
		$lastNumero = $vendaLast;
		
		$stdIde = new \stdClass();
		$stdIde->cUF = $config->cUF;
		$stdIde->cNF = rand(11111,99999);
		// $stdIde->natOp = $venda->natureza->natureza;
		$stdIde->natOp = $venda->natureza ? $venda->natureza->natureza : '';

		// $stdIde->indPag = 1; //NÃO EXISTE MAIS NA VERSÃO 4.00 // forma de pagamento

		$stdIde->mod = 55;
		$stdIde->serie = $config->numero_serie_nfe;
		$stdIde->nNF = (int)$lastNumero+1;
		$stdIde->dhEmi = date("Y-m-d\TH:i:sP");
		$stdIde->dhSaiEnt = date("Y-m-d\TH:i:sP");
		$stdIde->tpNF = 1;
		$stdIde->idDest = $config->UF != $venda->cliente->cidade->uf ? 2 : 1;
		$stdIde->cMunFG = $config->codMun;
		$stdIde->tpImp = 1;
		$stdIde->tpEmis = 1;
		$stdIde->cDV = 0;
		$stdIde->tpAmb = $config->ambiente;
		$stdIde->finNFe = 1;
		$stdIde->indFinal = $venda->cliente->consumidor_final;
		$stdIde->indPres = 1;
		$stdIde->procEmi = '0';
		$stdIde->verProc = '2.0';
		// $stdIde->dhCont = null;
		// $stdIde->xJust = null;


		//
		$tagide = $nfe->tagide($stdIde);

		$stdEmit = new \stdClass();
		$stdEmit->xNome = $config->razao_social;
		$stdEmit->xFant = $config->nome_fantasia;

		$ie = str_replace(".", "", $config->ie);
		$ie = str_replace("/", "", $ie);
		$ie = str_replace("-", "", $ie);
		$stdEmit->IE = $ie;
		$stdEmit->CRT = $tributacao->regime == 0 ? 1 : 3;

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$stdEmit->CNPJ = $cnpj;
		$stdEmit->IM = $ie;

		$emit = $nfe->tagemit($stdEmit);

		// ENDERECO EMITENTE
		$stdEnderEmit = new \stdClass();
		$stdEnderEmit->xLgr = $config->logradouro;
		$stdEnderEmit->nro = $config->numero;
		$stdEnderEmit->xCpl = "";
		
		$stdEnderEmit->xBairro = $config->bairro;
		$stdEnderEmit->cMun = $config->codMun;
		$stdEnderEmit->xMun = $config->municipio;
		$stdEnderEmit->UF = $config->UF;

		$cep = str_replace("-", "", $config->cep);
		$cep = str_replace(".", "", $cep);
		$stdEnderEmit->CEP = $cep;
		$stdEnderEmit->cPais = $config->codPais;
		$stdEnderEmit->xPais = $config->pais;

		$enderEmit = $nfe->tagenderEmit($stdEnderEmit);

		// DESTINATARIO
		$stdDest = new \stdClass();
		$stdDest->xNome = $venda->cliente->razao_social;

		if($venda->cliente->contribuinte){
			if($venda->cliente->ie_rg == 'ISENTO'){
				$stdDest->indIEDest = "2";
			}else{
				$stdDest->indIEDest = "1";
			}
			
		}else{
			$stdDest->indIEDest = "9";
		}


		$cnpj_cpf = str_replace(".", "", $venda->cliente->cpf_cnpj);
		$cnpj_cpf = str_replace("/", "", $cnpj_cpf);
		$cnpj_cpf = str_replace("-", "", $cnpj_cpf);

		if(strlen($cnpj_cpf) == 14){
			$stdDest->CNPJ = $cnpj_cpf;
			$ie = str_replace(".", "", $venda->cliente->ie_rg);
			$ie = str_replace("/", "", $ie);
			$ie = str_replace("-", "", $ie);
			$stdDest->IE = $ie;
		}
		else{
			$stdDest->CPF = $cnpj_cpf;
		} 

		$dest = $nfe->tagdest($stdDest);

		$stdEnderDest = new \stdClass();
		$stdEnderDest->xLgr = $venda->cliente->rua;
		$stdEnderDest->nro = $venda->cliente->numero;
		$stdEnderDest->xCpl = "";
		$stdEnderDest->xBairro = $venda->cliente->bairro;
		$stdEnderDest->cMun = $venda->cliente->cidade->codigo;
		$stdEnderDest->xMun = strtoupper($venda->cliente->cidade->nome);
		$stdEnderDest->UF = $venda->cliente->cidade->uf;

		$cep = str_replace("-", "", $venda->cliente->cep);
		$cep = str_replace(".", "", $cep);
		$stdEnderDest->CEP = $cep;
		$stdEnderDest->cPais = "1058";
		$stdEnderDest->xPais = "BRASIL";

		$enderDest = $nfe->tagenderDest($stdEnderDest);

		$somaProdutos = 0;
		$somaICMS = 0;
		//PRODUTOS
		$itemCont = 0;

		$totalItens = count($venda->itens);
		$somaFrete = 0;
		$somaDesconto = 0;
		$somaISS = 0;
		$somaServico = 0;
		foreach($venda->itens as $i){
			$itemCont++;

			$stdProd = new \stdClass();
			$stdProd->item = $itemCont;
			$stdProd->cEAN = $i->produto->codBarras;
			$stdProd->cEANTrib = $i->produto->codBarras;
			$stdProd->cProd = $i->produto->id;
			$stdProd->xProd = $i->produto->nome;
			$ncm = $i->produto->NCM;
			$ncm = str_replace(".", "", $ncm);

			if($i->produto->perc_iss > 0){
				$stdProd->NCM = '00';
			}else{
				$stdProd->NCM = $ncm;
			}
			
			$stdProd->CFOP = $config->UF != $venda->cliente->cidade->uf ?
			$i->produto->CFOP_saida_inter_estadual : $i->produto->CFOP_saida_estadual;


			$cest = $i->produto->CEST;
			$cest = str_replace(".", "", $cest);
			$stdProd->CEST = $cest;

			$stdProd->uCom = $i->produto->unidade_venda;
			$stdProd->qCom = $i->quantidade;
			$stdProd->vUnCom = $this->format($i->valor);
			$stdProd->vProd = $this->format(($i->quantidade * $i->valor));
			$stdProd->uTrib = $i->produto->unidade_venda;
			$stdProd->qTrib = $i->quantidade;
			$stdProd->vUnTrib = $this->format($i->desconto);
			$stdProd->indTot = $i->produto->perc_iss > 0 ? 0 : 1;
			$somaProdutos += ($i->quantidade * $i->valor);
			if($venda->desconto > 0){
				if($itemCont < sizeof($venda->itens)){
					$stdProd->vDesc = $this->format($venda->desconto/$totalItens);
					$somaDesconto += $venda->desconto/$totalItens;
				}else{
					$stdProd->vDesc = $venda->desconto - $somaDesconto;
				}
			}

			if($venda->frete){
				if($venda->frete->valor > 0){
					$somaFrete += $vFt = $venda->frete->valor/$totalItens;
					$stdProd->vFrete = $this->format($vFt);
				}
			}

			$prod = $nfe->tagprod($stdProd);

		//TAG IMPOSTO

			$stdImposto = new \stdClass();
			$stdImposto->item = $itemCont;
			if($i->produto->perc_iss > 0){
				$stdImposto->vTotTrib = 0.00;
			}

			$imposto = $nfe->tagimposto($stdImposto);

			// ICMS
			if($i->produto->perc_iss == 0){
				// regime normal
				if($tributacao->regime == 1){ 

				//$venda->produto->CST  CST

					$stdICMS = new \stdClass();
					$stdICMS->item = $itemCont; 
					$stdICMS->orig = 0;
					$stdICMS->CST = $i->produto->CST_CSOSN;
					$stdICMS->modBC = 0;
					$stdICMS->vBC = $this->format($i->valor * $i->quantidade);
					$stdICMS->pICMS = $this->format($i->produto->perc_icms);
					$stdICMS->vICMS = $stdICMS->vBC * ($stdICMS->pICMS/100);

					$somaICMS += (($i->valor * $i->quantidade) 
						* ($stdICMS->pICMS/100));
					$ICMS = $nfe->tagICMS($stdICMS);
					// regime simples
				}else{ 

				//$venda->produto->CST CSOSN

					$stdICMS = new \stdClass();

					$stdICMS->item = $itemCont; 
					$stdICMS->orig = 0;
					$stdICMS->CSOSN = $i->produto->CST_CSOSN;

					if($i->produto->CST_CSOSN == '500'){
						$stdICMS->vBCSTRet = 0.00;
						$stdICMS->pST = 0.00;
						$stdICMS->vICMSSTRet = 0.00;
					}

					$stdICMS->pCredSN = $this->format($i->produto->perc_icms);
					$stdICMS->vCredICMSSN = $this->format($i->produto->perc_icms);
					$ICMS = $nfe->tagICMSSN($stdICMS);

					$somaICMS = 0;
				}
			} 

			else
			{
				$valorIss = $i->valor * $i->quantidade;
				$somaServico += $valorIss;
				$valorIss = $valorIss * ($i->produto->perc_iss/100);
				$somaISS += $valorIss;


				$std = new \stdClass();
				$std->item = $itemCont; 
				$std->vBC = $stdProd->vProd;
				$std->vAliq = $i->produto->perc_iss;
				$std->vISSQN = $this->format($valorIss);
				$std->cMunFG = $config->codMun;
				$std->cListServ = $i->produto->cListServ;
				$std->indISS = 1;
				$std->indIncentivo = 1;

				$nfe->tagISSQN($std);
			}

				//PIS
			$stdPIS = new \stdClass();
			$stdPIS->item = $itemCont; 
			$stdPIS->CST = $i->produto->CST_PIS;
			$stdPIS->vBC = $this->format($i->produto->perc_pis) > 0 ? $stdProd->vProd : 0.00;
			$stdPIS->pPIS = $this->format($i->produto->perc_pis);
			$stdPIS->vPIS = $this->format(($stdProd->vProd * $i->quantidade) * 
				($i->produto->perc_pis/100));
			$PIS = $nfe->tagPIS($stdPIS);

				//COFINS
			$stdCOFINS = new \stdClass();
			$stdCOFINS->item = $itemCont; 
			$stdCOFINS->CST = $i->produto->CST_COFINS;
			$stdCOFINS->vBC = $this->format($i->produto->perc_cofins) > 0 ? $stdProd->vProd : 0.00;
			$stdCOFINS->pCOFINS = $this->format($i->produto->perc_cofins);
			$stdCOFINS->vCOFINS = $this->format(($stdProd->vProd * $i->quantidade) * 
				($i->produto->perc_cofins/100));
			$COFINS = $nfe->tagCOFINS($stdCOFINS);


				//IPI

			$std = new \stdClass();
			$std->item = $itemCont; 
				//999 – para tributação normal IPI
			$std->cEnq = '999'; 
			$std->CST = $i->produto->CST_IPI;
			$std->vBC = $this->format($i->produto->perc_ipi) > 0 ? $stdProd->vProd : 0.00;
			$std->pIPI = $this->format($i->produto->perc_ipi);
			$std->vIPI = $stdProd->vProd * $this->format(($i->produto->perc_ipi/100));

			$nfe->tagIPI($std);
			


			//TAG ANP

			if(strlen($i->produto->descricao_anp) > 5){
				$stdComb = new \stdClass();
				$stdComb->item = 1; 
				$stdComb->cProdANP = $i->produto->codigo_anp;
				$stdComb->descANP = $i->produto->descricao_anp; 
				$stdComb->UFCons = $venda->cliente->cidade->uf;

				$nfe->tagcomb($stdComb);
			}

			
		}


		$stdICMSTot = new \stdClass();
		$stdICMSTot->vProd = 0;
		$stdICMSTot->vBC = $tributacao->regime == 1 ? $this->format($somaProdutos) : 0.00;
		$stdICMSTot->vICMS = $this->format($somaICMS);
		$stdICMSTot->vICMSDeson = 0.00;
		$stdICMSTot->vBCST = 0.00;
		$stdICMSTot->vST = 0.00;

		if($venda->frete) $stdICMSTot->vFrete = $this->format($venda->frete->valor);
		else $stdICMSTot->vFrete = 0.00;

		$stdICMSTot->vSeg = 0.00;
		$stdICMSTot->vDesc = $this->format($venda->desconto);
		$stdICMSTot->vII = 0.00;
		$stdICMSTot->vIPI = 0.00;
		$stdICMSTot->vPIS = 0.00;
		$stdICMSTot->vCOFINS = 0.00;
		$stdICMSTot->vOutro = 0.00;
		
		if($venda->frete){
			$stdICMSTot->vNF = 
			$this->format(($somaProdutos+$venda->frete->valor)-$venda->desconto);
		} 
		else $stdICMSTot->vNF = $this->format($somaProdutos-$venda->desconto);

		$stdICMSTot->vTotTrib = 0.00;
		$ICMSTot = $nfe->tagICMSTot($stdICMSTot);

		//inicio totalizao issqn

		if($somaISS > 0){
			$std = new \stdClass();
			$std->vServ = $this->format($somaServico);
			$std->vBC = $this->format($somaServico);
			$std->vISS = $this->format($somaISS);
			$std->dCompet = date('Y-m-d');

			$std->cRegTrib = 6;

			$nfe->tagISSQNTot($std);
		}

		//fim totalizao issqn



		$stdTransp = new \stdClass();
		$stdTransp->modFrete = $venda->frete->tipo ?? '9';

		$transp = $nfe->tagtransp($stdTransp);


		if($venda->transportadora){
			$std = new \stdClass();
			$std->xNome = $venda->transportadora->razao_social;

			$std->xEnder = $venda->transportadora->logradouro;
			$std->xMun = strtoupper($venda->transportadora->cidade->nome);
			$std->UF = $venda->transportadora->cidade->uf;


			$cnpj_cpf = $venda->transportadora->cnpj_cpf;
			$cnpj_cpf = str_replace(".", "", $venda->transportadora->cnpj_cpf);
			$cnpj_cpf = str_replace("/", "", $cnpj_cpf);
			$cnpj_cpf = str_replace("-", "", $cnpj_cpf);

			if(strlen($cnpj_cpf) == 14) $std->CNPJ = $cnpj_cpf;
			else $std->CPF = $cnpj_cpf;

			$nfe->tagtransporta($std);
		}


		if($venda->frete != null){

			$std = new \stdClass();


			$placa = str_replace("-", "", $venda->frete->placa);
			$std->placa = strtoupper($placa);
			$std->UF = $venda->frete->uf;

			if($config->UF == $venda->cliente->cidade->uf){
				$nfe->tagveicTransp($std);
			}


			if($venda->frete->qtdVolumes > 0 && $venda->frete->peso_liquido > 0
				&& $venda->frete->peso_bruto > 0){
				$stdVol = new \stdClass();
				$stdVol->item = 1;
				$stdVol->qVol = $venda->frete->qtdVolumes;
				$stdVol->esp = $venda->frete->especie;

				$stdVol->nVol = $venda->frete->numeracaoVolumes;
				$stdVol->pesoL = $venda->frete->peso_liquido;
				$stdVol->pesoB = $venda->frete->peso_bruto;
				$vol = $nfe->tagvol($stdVol);
			}
		}



		$stdResp = new \stdClass();
		$stdResp->CNPJ = '08543628000145'; 
		$stdResp->xContato= 'Slym';
		$stdResp->email = 'marcos05111993@gmail.com'; 
		$stdResp->fone = '43996347016'; 

		$nfe->taginfRespTec($stdResp);


	//Fatura
		if($somaISS == 0 && $venda->natureza->CFOP_saida_estadual != '5915' && $venda->natureza->CFOP_saida_inter_estadual != '6915'){
			$stdFat = new \stdClass();
			$stdFat->nFat = (int)$lastNumero+1;
			$stdFat->vOrig = $this->format($somaProdutos);
			$stdFat->vDesc = $this->format($venda->desconto);
			$stdFat->vLiq = $this->format($somaProdutos-$venda->desconto);

			$fatura = $nfe->tagfat($stdFat);
		}

	//Duplicata
		if($somaISS == 0 && $venda->natureza->CFOP_saida_estadual != '5915' && $venda->natureza->CFOP_saida_inter_estadual != '6915'){
			if(count($venda->duplicatas) > 0){
				$contFatura = 1;
				foreach($venda->duplicatas as $ft){
					$stdDup = new \stdClass();
					$stdDup->nDup = "00".$contFatura;
					$stdDup->dVenc = substr($ft->data_vencimento, 0, 10);
					$stdDup->vDup = $this->format($ft->valor_integral);

					$nfe->tagdup($stdDup);
					$contFatura++;
				}
			}else{
				$stdDup = new \stdClass();
				$stdDup->nDup = '001';
				$stdDup->dVenc = Date('Y-m-d');
				$stdDup->vDup =  $this->format($somaProdutos-$venda->desconto);

				$nfe->tagdup($stdDup);
			}
		}



		$stdPag = new \stdClass();
		$pag = $nfe->tagpag($stdPag);

		$stdDetPag = new \stdClass();


		$stdDetPag->tPag = $venda->tipo_pagamento;
		$stdDetPag->vPag = $this->format($stdProd->vProd - $venda->desconto); 

		if($venda->tipo_pagamento == '03' || $venda->tipo_pagamento == '04'){
			$stdDetPag->CNPJ = '12345678901234';
			$stdDetPag->tBand = '01';
			$stdDetPag->cAut = '3333333';
			$stdDetPag->tpIntegra = 1;
		}
		$stdDetPag->indPag = $venda->forma_pagamento == 'a_vista' ?  0 : 1; 

		$detPag = $nfe->tagdetPag($stdDetPag);



		$stdInfoAdic = new \stdClass();
		$stdInfoAdic->infCpl = $venda->observacao;

		$infoAdic = $nfe->taginfAdic($stdInfoAdic);



		$std = new \stdClass();
		$std->CNPJ = getenv('RESP_CNPJ'); //CNPJ da pessoa jurídica responsável pelo sistema utilizado na emissão do documento fiscal eletrônico
		$std->xContato= getenv('RESP_NOME'); //Nome da pessoa a ser contatada
		$std->email = getenv('RESP_EMAIL'); //E-mail da pessoa jurídica a ser contatada
		$std->fone = getenv('RESP_FONE'); //Telefone da pessoa jurídica/física a ser contatada
		$nfe->taginfRespTec($std);
		
		if(getenv("AUTXML")){
			$std = new \stdClass();
			$std->CNPJ = getenv("AUTXML"); 
			$std->CPF = null;
			$nfe->tagautXML($std);
		}

		if($nfe->montaNFe()){
			$arr = [
				'chave' => $nfe->getChave(),
				'xml' => $nfe->getXML(),
				'nNf' => $stdIde->nNF
			];
			return $arr;
		} else {
			throw new Exception("Erro ao gerar NFe");
		}

	}


	
}
