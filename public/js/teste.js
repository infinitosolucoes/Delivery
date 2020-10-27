$('#testar').click(() => {
	$('#preloader').css('display', 'block')
	
	$.ajax
	({
		type: 'GET',
		url: path + 'configNF/teste',
		dataType: 'json',
		success: function(e){
			if(e.status == 200){
				// alert('Ambiente ok')
				swal("Sucesso", 'Ambiente ok', "success")

			}
		}, error: function(e){
			if(e.status == 200){
				$('#preloader').css('display', 'none')

				// alert('Ambiente ok')
				swal("Sucesso", 'Ambiente ok', "success")
				.then((v) => {

					alert(e.responseText)
				})

			}else{
				$('#preloader').css('display', 'none')

				// alert('Algo esta errado, verifique o console do navegador!')
				swal("Erro", 'Algo esta errado, verifique o console do navegador!', "warning")

				console.log(e)
			}

		}
	});
})