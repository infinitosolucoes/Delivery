
$(function(){
	if($('#pessoaFisica').is(':checked')){
		$('#cpf_cnpj').mask('000.000.000-00', {reverse: true});
		$('#lbl_cpf_cnpj').html('CPF');
		$('#lbl_ie_rg').html('RG');

		$('#sintegra').css('display', 'none')
	}else{
		$('#cpf_cnpj').mask('00.000.000/0000-00', {reverse: true});
		$('#lbl_cpf_cnpj').html('CNPJ');
		$('#lbl_ie_rg').html('IE');
		$('#sintegra').css('display', 'block');

	}

	// setTimeout(() => {
	// 	Materialize.updateTextFields();
	// }, 2000)
	
});
$('#pessoaFisica').click(function(){
	$('#lbl_cpf_cnpj').html('CPF');
	$('#lbl_ie_rg').html('RG');
	$('#cpf_cnpj').mask('000.000.000-00', {reverse: true});
	$('#sintegra').css('display', 'none')

})

$('#pessoaJuridica').click(function(){
	$('#lbl_cpf_cnpj').html('CNPJ');
	$('#lbl_ie_rg').html('IE');
	$('#cpf_cnpj').mask('00.000.000/0000-00', {reverse: true});
	$('#sintegra').css('display', 'block');
});

function consultaCadastro(){
	let cnpj = $('#cpf_cnpj').val();
	let uf = $('#sigla_uf').val();
	cnpj = cnpj.replace('.', '');
	cnpj = cnpj.replace('.', '');
	cnpj = cnpj.replace('-', '');
	cnpj = cnpj.replace('/', '');

	if(cnpj.length == 14 && uf.length != '--'){
		$('#preloader1').css('display', 'block')

		$.ajax
		({
			type: 'GET',
			data: {
				cnpj: cnpj,
				uf: uf
			},
			url: path + 'nf/consultaCadastro',

			dataType: 'json',

			success: function(e){
				$('#preloader1').css('display', 'none')
				console.log(e)
				if(e.infCons.infCad){
					let info = e.infCons.infCad;
					console.log(info)

					$('#ie_rg').val(info.IE)
					$('#razao_social').val(info.xNome)
					$('#nome_fantasia').val(info.xFant ? info.xFant : info.xNome)

					$('#rua').val(info.ender.xLgr)
					$('#numero').val(info.ender.nro)
					$('#bairro').val(info.ender.xBairro)
					let cep = info.ender.CEP;
					$('#cep').val(cep.substring(0, 5) + '-' + cep.substring(5, 9))
					Materialize.updateTextFields();
					findNomeCidade(info.ender.xMun, (res) => {
						let jsCidade = JSON.parse(res);
						console.log(jsCidade)
						if(jsCidade){
							console.log(jsCidade.id +" - "+jsCidade.nome)
							$('#autocomplete-cidade').val(jsCidade.id +" - "+jsCidade.nome)

							Materialize.updateTextFields();
						}
					})
					
				}else{
					// alert(e.infCons.xMotivo)
					swal("Erro", e.infCons.xMotivo, "warning")

				}
			}, error: function(e){
				console.log(e)
				$('#preloader1').css('display', 'none')
				// alert('CNJ e/ou UF inválido(s)')
				swal("Erro", 'CNJ e/ou UF inválido(s)', "warning")


			}

		});
	}
}

function findNomeCidade(nomeCidade, call){
	$.get(path + '/cidades/findNome/' + nomeCidade )
	.done((success) => {
		call(success)
	})
	.fail((err) => {
		call(err)
	})
}