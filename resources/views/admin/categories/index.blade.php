@extends('layouts.admin')
@section('title', 'ক্যাটাগরি ব্যবস্থাপনা')
@section('content')

<div class="pagehead">
    <div>
        <h1>ক্যাটাগরি ব্যবস্থাপনা</h1>
        <div class="sub">Total {{ $categories->total() }} categories</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <form id="bulk-category-form" method="POST" action="{{ route('admin.categories.bulk-delete') }}" onsubmit="return confirmBulkDelete('category')">
            @csrf
            <button class="btn secondary" type="submit"><i class="fa-solid fa-trash"></i> নির্বাচিত মুছুন</button>
        </form>
        <a class="btn" href="{{ route('admin.categories.create') }}"><i class="fa-solid fa-plus"></i> নতুন ক্যাটাগরি</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-error">{{ session('error') }}</div>
@endif

<section class="panel">
    <div class="tablewrap">
        <table class="table">
            <thead>
                <tr>
                    <th><input type="checkbox" data-select-all="category"></th>
                    <th>নাম</th>
                    <th>প্যারেন্ট</th>
                    <th>স্লাগ</th>
                    <th>পণ্য সংখ্যা</th>
                    <th>ক্রম</th>
                    <th>স্ট্যাটাস</th>
                    <th>অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $cat)
                    <tr>
                        <td><input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" form="bulk-category-form" data-row-select="category"></td>
                        <td>
                            @if($cat->parent_id)
                                <span style="padding-left:16px;color:var(--muted)">↳</span>
                            @endif
                            <b>{{ $cat->name }}</b>
                        </td>
                        <td>{{ $cat->parent->name ?? '—' }}</td>
                        <td><code>{{ $cat->slug }}</code></td>
                        <td>{{ $cat->products_count }}</td>
                        <td>{{ $cat->sort_order }}</td>
                        <td>
                            @if($cat->is_active)
                                <span class="status active">সক্রিয়</span>
                            @else
                                <span class="status inactive">নিষ্ক্রিয়</span>
                            @endif
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('categories.show', $cat) }}" target="_blank" class="iconbtn" title="স্টোরে দেখুন"><i class="fa-solid fa-eye"></i></a>
                                <a href="{{ route('admin.categories.edit', $cat) }}" class="iconbtn" title="এডিট করুন"><i class="fa-solid fa-pen"></i></a>
                                <form method="POST" action="{{ route('admin.categories.destroy', $cat) }}" onsubmit="return confirm('এই ক্যাটাগরি মুছে ফেলবেন?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="iconbtn danger" title="মুছুন"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">কোনো ক্যাটাগরি পাওয়া যায়নি।</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($categories->hasPages())
        <div style="padding:16px 20px">{{ $categories->links() }}</div>
    @endif
</section>
<script>
    function confirmBulkDelete(type) {
        const selected = document.querySelectorAll(`[data-row-select="${type}"]:checked`);
        if (!selected.length) {
            alert('অন্তত একটি ক্যাটাগরি নির্বাচন করুন।');
            return false;
        }

        return confirm(`নির্বাচিত ${selected.length}টি ক্যাটাগরি মুছে ফেলবেন? খালি ক্যাটাগরি মুছে যাবে, পণ্য থাকা ক্যাটাগরি বাদ যাবে।`);
    }

    document.querySelector('[data-select-all="category"]')?.addEventListener('change', (event) => {
        document.querySelectorAll('[data-row-select="category"]').forEach((checkbox) => { checkbox.checked = event.target.checked; });
    });
</script>
@endsection
