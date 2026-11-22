@extends('layouts.auth')
@section('title', 'নতুন পাসওয়ার্ড')
@section('content')
<section class="auth-card"><h2>নতুন পাসওয়ার্ড দিন</h2><form method="POST" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><label>ইমেইল<input type="email" name="email" value="{{ old('email', $email) }}" required></label><label>নতুন পাসওয়ার্ড<input type="password" name="password" minlength="8" required autocomplete="new-password"></label><label>পাসওয়ার্ড নিশ্চিত করুন<input type="password" name="password_confirmation" minlength="8" required autocomplete="new-password"></label>@error('email')<small class="error">{{ $message }}</small>@enderror<button type="submit">পাসওয়ার্ড সংরক্ষণ</button></form></section>
@endsection
