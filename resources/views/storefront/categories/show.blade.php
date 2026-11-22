@extends('layouts.storefront')

@section('title', $category->name)

@section('content')
<div class="page">
    <div class="breadcrumb">
        <a href="{{ route('home') }}">হোম</a>
        @if($category->parent)
            / <a href="{{ route('categories.show', $category->parent) }}">{{ $category->parent->name }}</a>
        @endif
        / {{ $category->name }}
    </div>

    <div class="category-page-header">
        <h1 class="page-title">{{ $category->name }}</h1>
        @if($category->description)
            <p class="muted">{{ $category->description }}</p>
        @endif
    </div>

    @if($category->children->isNotEmpty())
        <div class="subcategory-nav">
            @foreach($category->children as $child)
                <a href="{{ route('categories.show', $child) }}" class="subcategory-chip">
                    {{ $child->name }}
                </a>
            @endforeach
        </div>
    @endif

    <div class="products">
        @forelse($products as $product)
            @include('storefront.components.product-card', compact('product'))
        @empty
            <div class="panel empty">
                <i class="fa-solid fa-box-open" style="font-size:36px;color:var(--muted);margin-bottom:12px"></i>
                <p>এই ক্যাটাগরিতে এখনো কোনো পণ্য যোগ করা হয়নি।</p>
                <a href="{{ route('home') }}" style="color:var(--green)">হোমে ফিরে যান</a>
            </div>
        @endforelse
    </div>

    @if($products->hasPages())
        <div class="pagination-wrap">
            {{ $products->links() }}
        </div>
    @endif
</div>
@endsection
