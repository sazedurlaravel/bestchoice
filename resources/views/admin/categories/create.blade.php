@extends('layouts.admin')
@section('title', 'নতুন ক্যাটাগরি')
@section('content')
<div class="pagehead">
    <div><h1>নতুন ক্যাটাগরি</h1><div class="sub"><a href="{{ route('admin.categories') }}">← ক্যাটাগরি তালিকায় ফিরুন</a></div></div>
</div>
<form method="POST" action="{{ route('admin.categories.store') }}" enctype="multipart/form-data" style="max-width:600px">
    @csrf
    @include('admin.categories._form')
    <div class="form-actions">
        <button type="submit" class="btn">সংরক্ষণ করুন</button>
        <a href="{{ route('admin.categories') }}" class="btn secondary">বাতিল</a>
    </div>
</form>
@endsection
