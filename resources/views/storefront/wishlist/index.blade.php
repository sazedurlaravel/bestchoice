@extends('layouts.storefront')
@section('title', 'পছন্দের তালিকা')
@section('content')
<div class="page"><div class="breadcrumb"><a href="{{ route('home') }}">হোম</a> / পছন্দের তালিকা</div><h1 class="page-title">পছন্দের তালিকা</h1><div class="products">@forelse($products as $product) @include('storefront.components.product-card', compact('product')) @empty<div class="panel empty">আপনার পছন্দের তালিকা এখনো খালি।</div>@endforelse</div></div>
@endsection
