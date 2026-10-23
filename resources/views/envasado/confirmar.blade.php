@extends('layouts.app')
@section('title','Envasado')
@section('page-title','Envasado')
@push('styles')<link rel="stylesheet" href="{{ asset('css/envasado.css') }}">@endpush
@section('content')
<section class="form-section envasado-seccion">@include('envasado.partials.confirmacion')</section>
@endsection
