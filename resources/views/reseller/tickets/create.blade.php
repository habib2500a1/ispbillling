@extends('reseller.layout')

@section('title', 'New ticket')

@section('content')
    <div class="rsl-card p-6 max-w-2xl">
        <h1 class="rsl-title">Create support ticket</h1>
        <form method="post" action="{{ route('reseller.tickets.store') }}" class="mt-6 grid gap-4">
            @csrf
            <div>
                <label class="block text-xs font-bold uppercase rsl-text-muted">Subscriber</label>
                <select name="customer_id" required class="rsl-input mt-1">
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->customer_code }} — {{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="block text-xs font-bold uppercase rsl-text-muted">Subject</label><input name="subject" required class="rsl-input mt-1"></div>
            <div><label class="block text-xs font-bold uppercase rsl-text-muted">Description</label><textarea name="description" required rows="5" class="rsl-input mt-1"></textarea></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">Department</label><select name="department" class="rsl-input mt-1">@foreach ($departments as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">Priority</label><select name="priority" class="rsl-input mt-1">@foreach ($priorities as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            </div>
            <div><label class="block text-xs font-bold uppercase rsl-text-muted">Issue type</label><select name="issue_type" class="rsl-input mt-1"><option value="">—</option>@foreach ($issueTypes as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <button type="submit" class="rsl-btn">Submit ticket</button>
        </form>
    </div>
@endsection
