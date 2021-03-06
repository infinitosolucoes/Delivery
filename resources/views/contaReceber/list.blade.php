@extends('default.layout')
@section('content')

<style type="text/css">
.dismiss{

}
</style>
<div class="row">
	<div class="col s12">

		<h4>Lista de Contas a Receber</h4>
		<h6 class="blue-text">*{{$infoDados}}</h6>
		

		<div class="row"></div>
		<div class="row">
			<a href="/contasReceber/new" class="btn green accent-3">
				<i class="material-icons left">add</i>	
				Nova Conta a Receber		
			</a>
		</div> 

		@if(session()->has('message'))
		<div class="row">
			<div style="border-radius: 10px;" class="col s12 {{ session('color') }}">
				<h5 class="center-align white-text">{{ session()->get('message') }}</h5>
			</div>
		</div>
		@endif

		<form method="get" action="/contasReceber/filtro">
			<div class="row">
				<div class="col s4 input-field">
					<input value="{{{ isset($cliente) ? $cliente : '' }}}" type="text" class="validate" name="cliente">
					<label>Cliente</label>
				</div>

				<div class="col s2 input-field">
					<input value="{{{ isset($dataInicial) ? $dataInicial : '' }}}" type="text" class="datepicker" name="data_inicial">
					<label>Data Inicial</label>
				</div>
				<div class="col s2 input-field">
					<input value="{{{ isset($dataFinal) ? $dataFinal : '' }}}" type="text" class="datepicker" name="data_final">
					<label>Data Final</label>
				</div>

				<div class="col s2 input-field">
					<select name="status">
						<option @if(isset($stats) && $status == 'todos') selected @endif value="todos">TODOS</option>
						<option @if(isset($stats) && $status == 'pago') selected @endif value="pago">PAGO</option>
						<option @if(isset($stats) && $status == 'pendente') selected @endif value="pendente">PENDENTE</option>
					</select>
					<label>Estado</label>
				</div>

				<div class="col s2">
					<button type="submit" class="btn-large black">
						<i class="material-icons">search</i>
					</button>
				</div>
			</div>
		</form>



		<div class="row">
			<div class="col s12 m12 l12">
				<label>Numero de registros: {{count($contas)}}</label>					
			</div>
			
			<table class="">
				<thead>
					<tr>
						<th>Código</th>
						<th>Cliente</th>
						<th>Status</th>
						<th>Categoria</th>
						<th>Valor</th>
						<th>Valor Recebido</th>
						<th>Referencia</th>
						<th>Data de Registro</th>
						<th>Data de Vencimento</th>
						<th>Data de Recebimento</th>

					</tr>
				</thead>

				<tbody>
					<?php 
					$somaValor = 0;
					$somaRecebido = 0;
					?>
					@foreach($contas as $c)
					<tr>
						<th>{{ $c->id }}</th>
						@if($c->venda_id != null)
						<th>{{ $c->venda->cliente->razao_social }}</th>
						@else
						<th> -- </th>
						@endif
						<th>
							@if($c->status == true)
							<i class="material-icons green-text">brightness_1</i>
							@else
							<i class="material-icons red-text">brightness_1</i>
							@endif
						</th>
						<th>{{$c->categoria->nome}}</th>
						<th>{{ number_format($c->valor_integral, 2, ',', '.') }}</th>
						<th>{{ number_format($c->valor_recebido, 2, ',', '.') }}</th>


						<th>
							<a class="btn brown lighten-2 tooltipped" data-position="bottom" data-delay="50" data-tooltip="{{$c->referencia}}"
								@if(empty($c->referencia))
								disabled
								@endif
								>
								<i class="material-icons">message</i>

							</a>
						</th>
						<th>{{ \Carbon\Carbon::parse($c->data_registro)->format('d/m/Y')}}</th>
						<th>{{ \Carbon\Carbon::parse($c->data_vencimento)->format('d/m/Y')}}</th>
						@if($c->status == true)
						<th>{{ \Carbon\Carbon::parse($c->data_recebimento)->format('d/m/Y')}}</th>
						@else
						<th>--</th>
						@endif
						<th>
							<a href="/contasReceber/edit/{{ $c->id }}">
								<i class="material-icons left">edit</i>					
							</a>
							<a onclick = "if (! confirm('Deseja excluir este registro?')) { return false; }" href="/contasReceber/delete/{{ $c->id }}">
								<i class="material-icons left red-text">delete</i>					
							</a>
							@if($c->status == false)
							<a title="pagar" href="/contasReceber/receber/{{$c->id}}">
								<i class="material-icons left black-text">money</i>
							</a>
							@endif
						</th>
					</tr>

					<?php
					$somaValor += $c->valor_integral;
					$somaRecebido += $c->valor_recebido;
					?>
					@endforeach
					<tr class="green lighten-3">
						<td colspan="4" class="center-align">TOTAL</td>
						<td>{{ number_format($somaValor, 2, ',', '.') }}</td>
						<td>{{ number_format($somaRecebido, 2, ',', '.') }}</td>
						<td colspan="6"></td>
					</tr>
				</tbody>
			</table>
		</div>

		<h4>Valor a Receber: <strong>
			R$ {{number_format($somaValor - $somaRecebido, 2, ',', '.') }}
		</strong></h4>
		<input type="hidden" id="somaContas" value="{{json_encode($somaContas)}}">

		<br>

		@if(count($contas) > 0)
		
		@endif

	</div>
</div>


@endsection	