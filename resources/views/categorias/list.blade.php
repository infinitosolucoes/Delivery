@extends('default.layout')
@section('content')

<div class="row">
	<div class="col s12">

		<h4>Lista de Categorias</h4>

		@if(session()->has('message'))
		<div style="border-radius: 10px;" class="col s12 {{ session('color') }}">
			<h5 class="center-align white-text">{{ session()->get('message') }}</h5>
		</div>
		@endif

		<div class="row"></div>
		<div class="row">
			<a href="/categorias/new" class="btn green accent-3">
				<i class="material-icons left">add</i>	
				Nova Categoria		
			</a>
		</div>



		<div class="row">
			<div class="col s12">
				<label>Numero de registros: {{count($categorias)}}</label>					
			</div>
			<table class="col s12">
				<thead>
					<tr>
						<th>Código</th>
						<th>Nome</th>
					</tr>
				</thead>

				<tbody>
					@foreach($categorias as $c)
					<tr>
						<th>{{ $c->id }}</th>
						<th>{{ $c->nome }}</th>
						<th>
							<a href="/categorias/edit/{{ $c->id }}">
								<i class="material-icons left">edit</i>					
							</a>

							<a onclick='swal("Atenção!", "Deseja remover este registro?", "warning").then((sim) => {if(sim){ location.href="/categorias/delete/{{ $c->id }}" }else{return false} })' href="#!">
								<i class="material-icons left red-text">delete</i>					
							</a>
						</th>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>
@endsection	