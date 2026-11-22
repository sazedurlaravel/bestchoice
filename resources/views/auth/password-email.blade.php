@extends('layouts.auth')
@section('title', 'পাসওয়ার্ড রিসেট')
@section('content')
<section class="auth-card"><h2>পাসওয়ার্ড রিসেট</h2><p>আপনার অ্যাকাউন্টের ইমেইল দিলে রিসেট লিংক পাঠানো হবে।</p>
@if(session('success'))<p class="success">{{ session('success') }}</p>@endif @if(session('error'))<p class="error">{{ session('error') }}</p>@endif
<form method="POST" action="{{ route('password.email') }}">@csrf<label>ইমেইল<input type="email" name="email" value="{{ old('email') }}" required autofocus></label>@error('email')<small class="error">{{ $message }}</small>@enderror<button type="submit">রিসেট লিংক পাঠান</button></form><a href="{{ route('login') }}">লগইনে ফিরুন</a></section>
@endsection
