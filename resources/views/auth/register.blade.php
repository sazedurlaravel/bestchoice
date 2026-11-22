@extends('layouts.auth')

@section('title', 'অ্যাকাউন্ট তৈরি')

@section('content')
    <section class="auth-card">
        <h1>Best <span>Choice</span></h1>
        <p>নতুন কাস্টমার অ্যাকাউন্ট তৈরি করুন</p>

        <form method="POST" action="{{ route('register.store') }}">
            @csrf
            <label>নাম<input type="text" name="name" value="{{ old('name') }}" required autofocus></label>
            @error('name')<small class="error">{{ $message }}</small>@enderror
            <label>ইমেইল<input type="email" name="email" value="{{ old('email') }}" required></label>
            @error('email')<small class="error">{{ $message }}</small>@enderror
            <label>মোবাইল <small>(ঐচ্ছিক)</small><input type="tel" name="mobile" value="{{ old('mobile') }}"></label>
            @error('mobile')<small class="error">{{ $message }}</small>@enderror
            <label>পাসওয়ার্ড<input type="password" name="password" minlength="8" required></label>
            <label>পাসওয়ার্ড নিশ্চিত করুন<input type="password" name="password_confirmation" minlength="8" required></label>
            @error('password')<small class="error">{{ $message }}</small>@enderror
            <button type="submit">অ্যাকাউন্ট তৈরি করুন</button>
        </form>

        <p>ইতিমধ্যে অ্যাকাউন্ট আছে? <a href="{{ route('login') }}">লগইন করুন</a></p>
        <a href="{{ route('home') }}">← স্টোরফ্রন্টে ফিরুন</a>
    </section>
@endsection
