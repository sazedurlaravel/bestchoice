@extends('layouts.admin')
@section('title', 'ক্যাটাগরি এডিট করুন')
@section('content')
<div class="pagehead">
    <div><h1>ক্যাটাগরি এডিট করুন</h1><div class="sub"><a href="{{ route('admin.categories') }}">← ক্যাটাগরি তালিকায় ফিরুন</a></div></div>
</div>
<form method="POST" action="{{ route('admin.categories.update', $category) }}" enctype="multipart/form-data" style="max-width:600px">
    @csrf @method('PUT')
    @include('admin.categories._form', ['model' => $category])
    <div class="form-actions">
        <button type="submit" class="btn">পরিবর্তন সংরক্ষণ করুন</button>
        <a href="{{ route('admin.categories') }}" class="btn secondary">বাতিল</a>
    </div>
</form>
@endsection
