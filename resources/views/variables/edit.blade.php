@extends('app')
 
@section('content')
	<div class="module-wrapper edit-variable-module">
		<a class="back-btn" href="{{ route( 'variables.show', $variable->id ) }}"><i class="fa fa-arrow-left"></i>Back to the variable</a>
		<h2>Edit value</h2>
		{!! Form::model($variable, ['class' => 'validate-form', 'method' => 'PATCH', 'route' => ['variables.update', $variable->id]]) !!}
			<div class="form-group">
				{!! Form::label('name', 'Name:') !!}
				{!! Form::text('name', null, array('class' => 'form-control required')) !!}
			</div>
			<div class="form-group">
				{!! Form::label('unit', 'Unit:') !!}
				{!! Form::text('unit', null, array('class' => 'form-control')) !!}
			</div>
			<div class="form-group">
				{!! Form::label('coverage', 'Geographic Coverage:') !!}
				{!! Form::text('coverage', null, array('class' => 'form-control')) !!}
			</div>
			<div class="form-group">
				{!! Form::label('timespan', 'Time Span:') !!}
				{!! Form::text('timespan', null, array('class' => 'form-control')) !!}
			</div>
			<div class="form-group">
				{!! Form::label('description', 'Description:') !!}
				{!! Form::textarea('description', null, array('class' => 'form-control')) !!}
			</div>
			<div class="form-group">
				{!! Form::label('sourceId', 'Source:') !!}
				{!! Form::select('sourceId', $sources, ($variable->source)?$variable->source->id:null, array('class' => 'form-control')) !!}
			</div>
			<div class="form-group">
				{!! Form::submit('Update variable', ['class'=>'btn btn-success']) !!}
			</div>
	    {!! Form::close() !!}
	</div>
@endsection