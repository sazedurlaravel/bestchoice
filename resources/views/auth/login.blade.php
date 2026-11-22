@extends('layouts.auth')

@section('title', 'আমার অ্যাকাউন্ট')

@section('content')
    <header class="auth-brand">
        <h1>Best <span>Choice</span></h1>
        <p>আপনার শপিং অ্যাকাউন্ট</p>
    </header>

    <div class="auth-grid">
        <section class="auth-card">
            <h2>লগইন</h2>
            <p>আপনার অ্যাকাউন্টে প্রবেশ করুন</p>

            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <label>ইমেইল বা মোবাইল<input type="text" name="email" value="{{ old('email') }}" required autofocus></label>
                <label>পাসওয়ার্ড<input type="password" name="password" required></label>
                <a href="{{ route('password.request') }}">পাসওয়ার্ড ভুলে গেছেন?</a>
                <label class="auth-check"><input type="checkbox" name="remember"> মনে রাখুন</label>
                @error('email')<small class="error">{{ $message }}</small>@enderror
                <button type="submit">লগইন করুন</button>
            </form>
        </section>

        <section class="auth-card">
            <h2>নতুন অ্যাকাউন্ট</h2>
            <p>নতুন ক্রেতা হিসেবে রেজিস্টার করুন</p>

            <form method="POST" action="{{ route('register.store') }}">
                @csrf
                <label>নাম<input type="text" name="name" value="{{ old('name') }}" required></label>
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
        </section>
    </div>

    <a class="auth-home-link" href="{{ route('home') }}">← স্টোরফ্রন্টে ফিরুন</a>
@endsection
