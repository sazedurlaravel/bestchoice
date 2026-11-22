@extends('layouts.admin')
@section('content')<section class="panel placeholder"><span class="pill">PHASE 1</span><h2>{{ $title }}</h2><p>{{ $message }}</p><a class="primary" href="{{ route('admin.dashboard') }}">ড্যাশবোর্ডে ফিরুন</a></section>@endsection
